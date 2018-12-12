<?php

declare(strict_types=1);

namespace Doctrine\ORM\Mapping;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Platforms;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnClassMetadataNotFoundEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\Exception\InvalidCustomGenerator;
use Doctrine\ORM\Mapping\Exception\TableGeneratorNotImplementedYet;
use Doctrine\ORM\Mapping\Exception\UnknownGeneratorType;
use Doctrine\ORM\Sequencing;
use Doctrine\ORM\Sequencing\Planning\AssociationValueGeneratorExecutor;
use Doctrine\ORM\Sequencing\Planning\ColumnValueGeneratorExecutor;
use Doctrine\ORM\Sequencing\Planning\CompositeValueGenerationPlan;
use Doctrine\ORM\Sequencing\Planning\NoopValueGenerationPlan;
use Doctrine\ORM\Sequencing\Planning\SingleValueGenerationPlan;
use Doctrine\ORM\Sequencing\Planning\ValueGenerationExecutor;
use InvalidArgumentException;
use ReflectionException;
use function array_map;
use function class_exists;
use function count;
use function sprintf;

/**
 * The ClassMetadataFactory is used to create ClassMetadata objects that contain all the
 * metadata mapping information of a class which describes how a class should be mapped
 * to a relational database.
 */
class ClassMetadataFactory extends AbstractClassMetadataFactory
{
    /** @var EntityManagerInterface|null */
    private $em;

    /** @var AbstractPlatform */
    private $targetPlatform;

    /** @var Driver\MappingDriver */
    private $driver;

    /** @var EventManager */
    private $evm;

    /**
     * {@inheritdoc}
     */
    protected function loadMetadata(string $name, ClassMetadataBuildingContext $metadataBuildingContext) : array
    {
        $loaded = parent::loadMetadata($name, $metadataBuildingContext);

        array_map([$this, 'resolveDiscriminatorValue'], $loaded);

        return $loaded;
    }

    public function setEntityManager(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ORMException
     */
    protected function initialize() : void
    {
        $this->driver      = $this->em->getConfiguration()->getMetadataDriverImpl();
        $this->evm         = $this->em->getEventManager();
        $this->initialized = true;
    }

    /**
     * {@inheritdoc}
     */
    protected function onNotFoundMetadata(
        string $className,
        ClassMetadataBuildingContext $metadataBuildingContext
    ) : ?ClassMetadata {
        if (! $this->evm->hasListeners(Events::onClassMetadataNotFound)) {
            return null;
        }

        $eventArgs = new OnClassMetadataNotFoundEventArgs($className, $metadataBuildingContext, $this->em);

        $this->evm->dispatchEvent(Events::onClassMetadataNotFound, $eventArgs);

        return $eventArgs->getFoundMetadata();
    }

    /**
     * {@inheritdoc}
     *
     * @throws MappingException
     * @throws ORMException
     */
    protected function doLoadMetadata(
        string $className,
        ?ClassMetadata $parent,
        ClassMetadataBuildingContext $metadataBuildingContext
    ) : ClassMetadata {
        $classMetadata = new ClassMetadata($className, $metadataBuildingContext);

        if ($parent) {
            $classMetadata->setParent($parent);

            foreach ($parent->getDeclaredPropertiesIterator() as $fieldName => $property) {
                $classMetadata->addInheritedProperty($property);
            }

            $classMetadata->setInheritanceType($parent->inheritanceType);
            $classMetadata->setIdentifier($parent->identifier);

            if ($parent->discriminatorColumn) {
                $classMetadata->setDiscriminatorColumn($parent->discriminatorColumn);
                $classMetadata->setDiscriminatorMap($parent->discriminatorMap);
            }

            $classMetadata->setLifecycleCallbacks($parent->lifecycleCallbacks);
            $classMetadata->setChangeTrackingPolicy($parent->changeTrackingPolicy);

            if ($parent->isMappedSuperclass) {
                $classMetadata->setCustomRepositoryClassName($parent->getCustomRepositoryClassName());
            }
        }

        // Invoke driver
        try {
            $this->driver->loadMetadataForClass($classMetadata->getClassName(), $classMetadata, $metadataBuildingContext);
        } catch (ReflectionException $e) {
            throw MappingException::reflectionFailure($classMetadata->getClassName(), $e);
        }

        $this->completeIdentifierGeneratorMappings($classMetadata);

        if ($parent) {
            if ($parent->getCache()) {
                $classMetadata->setCache(clone $parent->getCache());
            }

            if (! empty($parent->entityListeners) && empty($classMetadata->entityListeners)) {
                $classMetadata->entityListeners = $parent->entityListeners;
            }
        }

        $this->completeRuntimeMetadata($classMetadata, $parent);

        if ($this->evm->hasListeners(Events::loadClassMetadata)) {
            $eventArgs = new LoadClassMetadataEventArgs($classMetadata, $this->em);

            $this->evm->dispatchEvent(Events::loadClassMetadata, $eventArgs);
        }

        $this->buildValueGenerationPlan($classMetadata);
        $this->validateRuntimeMetadata($classMetadata, $parent);

        return $classMetadata;
    }

    protected function completeRuntimeMetadata(ClassMetadata $class, ?ClassMetadata $parent = null) : void
    {
        if (! $parent || ! $parent->isMappedSuperclass) {
            return;
        }

        if ($class->isMappedSuperclass) {
            return;
        }

        $tableName = $class->getTableName();

        // Resolve column table names
        foreach ($class->getDeclaredPropertiesIterator() as $property) {
            if ($property instanceof FieldMetadata) {
                $property->setTableName($property->getTableName() ?? $tableName);

                continue;
            }

            if (! ($property instanceof ToOneAssociationMetadata)) {
                continue;
            }

            // Resolve association join column table names
            foreach ($property->getJoinColumns() as $joinColumn) {
                /** @var JoinColumnMetadata $joinColumn */
                $joinColumn->setTableName($joinColumn->getTableName() ?? $tableName);
            }
        }
    }

    /**
     * Validate runtime metadata is correctly defined.
     *
     * @throws MappingException
     */
    protected function validateRuntimeMetadata(ClassMetadata $class, ?ClassMetadata $parent = null) : void
    {
        if (! $class->getReflectionClass()) {
            // only validate if there is a reflection class instance
            return;
        }

        $class->validateIdentifier();
        $class->validateAssociations();
        $class->validateLifecycleCallbacks($this->getReflectionService());

        // verify inheritance
        if (! $class->isMappedSuperclass && $class->inheritanceType !== InheritanceType::NONE) {
            if (! $parent) {
                if (! $class->discriminatorMap) {
                    throw MappingException::missingDiscriminatorMap($class->getClassName());
                }

                if (! $class->discriminatorColumn) {
                    throw MappingException::missingDiscriminatorColumn($class->getClassName());
                }
            }
        } elseif (($class->discriminatorMap || $class->discriminatorColumn) && $class->isMappedSuperclass && $class->isRootEntity()) {
            // second condition is necessary for mapped superclasses in the middle of an inheritance hierarchy
            throw MappingException::noInheritanceOnMappedSuperClass($class->getClassName());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function newClassMetadataBuildingContext() : ClassMetadataBuildingContext
    {
        return new ClassMetadataBuildingContext(
            $this,
            $this->getReflectionService(),
            $this->em->getConfiguration()->getNamingStrategy()
        );
    }

    /**
     * Populates the discriminator value of the given metadata (if not set) by iterating over discriminator
     * map classes and looking for a fitting one.
     *
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws MappingException
     */
    private function resolveDiscriminatorValue(ClassMetadata $metadata) : void
    {
        if ($metadata->discriminatorValue || ! $metadata->discriminatorMap || $metadata->isMappedSuperclass ||
            ! $metadata->getReflectionClass() || $metadata->getReflectionClass()->isAbstract()) {
            return;
        }

        // minor optimization: avoid loading related metadata when not needed
        foreach ($metadata->discriminatorMap as $discriminatorValue => $discriminatorClass) {
            if ($discriminatorClass === $metadata->getClassName()) {
                $metadata->discriminatorValue = $discriminatorValue;

                return;
            }
        }

        // iterate over discriminator mappings and resolve actual referenced classes according to existing metadata
        foreach ($metadata->discriminatorMap as $discriminatorValue => $discriminatorClass) {
            if ($metadata->getClassName() === $this->getMetadataFor($discriminatorClass)->getClassName()) {
                $metadata->discriminatorValue = $discriminatorValue;

                return;
            }
        }

        throw MappingException::mappedClassNotPartOfDiscriminatorMap($metadata->getClassName(), $metadata->getRootClassName());
    }

    /**
     * Completes the ID generator mapping. If "auto" is specified we choose the generator
     * most appropriate for the targeted database platform.
     *
     * @throws ORMException
     */
    private function completeIdentifierGeneratorMappings(ClassMetadata $class) : void
    {
        foreach ($class->getDeclaredPropertiesIterator() as $property) {
            if (! $property instanceof FieldMetadata /*&& ! $property instanceof AssocationMetadata*/) {
                continue;
            }

            $this->completeFieldIdentifierGeneratorMapping($property);
        }
    }

    private function completeFieldIdentifierGeneratorMapping(FieldMetadata $field)
    {
        if (! $field->hasValueGenerator()) {
            return;
        }

        $platform  = $this->getTargetPlatform();
        $class     = $field->getDeclaringClass();
        $generator = $field->getValueGenerator();

        if ($generator->getType() === GeneratorType::AUTO) {
            $generator = new ValueGeneratorMetadata(
                $platform->prefersSequences()
                    ? GeneratorType::SEQUENCE
                    : ($platform->prefersIdentityColumns()
                        ? GeneratorType::IDENTITY
                        : GeneratorType::TABLE
                ),
                $field->getValueGenerator()->getDefinition()
            );
            $field->setValueGenerator($generator);
        }

        // Validate generator definition and set defaults where needed
        switch ($generator->getType()) {
            case GeneratorType::SEQUENCE:
                // If there is no sequence definition yet, create a default definition
                if ($generator->getDefinition()) {
                    break;
                }

                // @todo guilhermeblanco Move sequence generation to DBAL
                $sequencePrefix = $platform->getSequencePrefix($field->getTableName(), $field->getSchemaName());
                $idSequenceName = sprintf('%s_%s_seq', $sequencePrefix, $field->getColumnName());
                $sequenceName   = $platform->fixSchemaElementName($idSequenceName);

                $field->setValueGenerator(
                    new ValueGeneratorMetadata(
                        $generator->getType(),
                        [
                            'sequenceName'   => $sequenceName,
                            'allocationSize' => 1,
                        ]
                    )
                );

                break;

            case GeneratorType::TABLE:
                throw TableGeneratorNotImplementedYet::create();
                break;

            case GeneratorType::CUSTOM:
                $definition = $generator->getDefinition();
                if (! isset($definition['class'])) {
                    throw InvalidCustomGenerator::onClassNotConfigured();
                }
                if (! class_exists($definition['class'])) {
                    throw InvalidCustomGenerator::onMissingClass($definition);
                }

                break;

            case GeneratorType::IDENTITY:
            case GeneratorType::NONE:
                break;

            default:
                throw UnknownGeneratorType::create($generator->getType());
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getDriver() : Driver\MappingDriver
    {
        return $this->driver;
    }

    /**
     * {@inheritDoc}
     */
    protected function isEntity(ClassMetadata $class) : bool
    {
        return isset($class->isMappedSuperclass) && $class->isMappedSuperclass === false;
    }

    private function getTargetPlatform() : Platforms\AbstractPlatform
    {
        if (! $this->targetPlatform) {
            $this->targetPlatform = $this->em->getConnection()->getDatabasePlatform();
        }

        return $this->targetPlatform;
    }

    private function buildValueGenerationPlan(ClassMetadata $class) : void
    {
        $executors = $this->buildValueGenerationExecutorList($class);

        switch (count($executors)) {
            case 0:
                $class->setValueGenerationPlan(new NoopValueGenerationPlan());
                break;

            case 1:
                $class->setValueGenerationPlan(new SingleValueGenerationPlan($class, $executors[0]));
                break;

            default:
                $class->setValueGenerationPlan(new CompositeValueGenerationPlan($class, $executors));
                break;
        }
    }

    /**
     * @return ValueGenerationExecutor[]
     */
    private function buildValueGenerationExecutorList(ClassMetadata $class) : array
    {
        $executors = [];

        foreach ($class->getDeclaredPropertiesIterator() as $property) {
            $executor = $this->buildValueGenerationExecutorForProperty($class, $property);

            if ($executor instanceof ValueGenerationExecutor) {
                $executors[] = $executor;
            }
        }

        return $executors;
    }

    private function buildValueGenerationExecutorForProperty(
        ClassMetadata $class,
        Property $property
    ) : ?ValueGenerationExecutor {
        if ($property instanceof LocalColumnMetadata && $property->hasValueGenerator()) {
            return new ColumnValueGeneratorExecutor($property, $this->createPropertyValueGenerator($class, $property));
        }

        if ($property instanceof ToOneAssociationMetadata && $property->isPrimaryKey()) {
            return new AssociationValueGeneratorExecutor();
        }

        return null;
    }

    private function createPropertyValueGenerator(
        ClassMetadata $class,
        LocalColumnMetadata $property
    ) : Sequencing\Generator {
        $platform = $this->getTargetPlatform();

        switch ($property->getValueGenerator()->getType()) {
            case GeneratorType::IDENTITY:
                $sequenceName = null;

                // Platforms that do not have native IDENTITY support need a sequence to emulate this behaviour.
                if ($platform->usesSequenceEmulatedIdentityColumns()) {
                    $sequencePrefix = $platform->getSequencePrefix($class->getTableName(), $class->getSchemaName());
                    $idSequenceName = $platform->getIdentitySequenceName($sequencePrefix, $property->getColumnName());
                    $sequenceName   = $platform->quoteIdentifier($platform->fixSchemaElementName($idSequenceName));
                }

                return $property->getTypeName() === 'bigint'
                    ? new Sequencing\BigIntegerIdentityGenerator($sequenceName)
                    : new Sequencing\IdentityGenerator($sequenceName);

            case GeneratorType::SEQUENCE:
                $definition = $property->getValueGenerator()->getDefinition();
                return new Sequencing\SequenceGenerator(
                    $platform->quoteIdentifier($definition['sequenceName']),
                    $definition['allocationSize']
                );
                break;

            case GeneratorType::CUSTOM:
                $class = $property->getValueGenerator()->getDefinition()['class'];
                return new $class();
                break;
        }
    }
}
