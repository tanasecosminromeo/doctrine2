<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Mapping;

use Doctrine\ORM\Annotation as ORM;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\GeneratorType;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Mapping\TransientMetadata;
use Doctrine\Tests\Models\DDC869\DDC869ChequePayment;
use Doctrine\Tests\Models\DDC869\DDC869CreditCardPayment;
use Doctrine\Tests\Models\DDC869\DDC869Payment;
use Doctrine\Tests\Models\DDC869\DDC869PaymentRepository;
use Doctrine\Tests\OrmTestCase;
use function iterator_to_array;

class BasicInheritanceMappingTest extends OrmTestCase
{
    /** @var ClassMetadataFactory */
    private $cmf;

    /**
     * {@inheritDoc}
     */
    protected function setUp() : void
    {
        $this->cmf = new ClassMetadataFactory();

        $this->cmf->setEntityManager($this->getTestEntityManager());
    }

    public function testGetMetadataForTransientClassThrowsException() : void
    {
        $this->expectException(MappingException::class);

        $this->cmf->getMetadataFor(TransientBaseClass::class);
    }

    public function testGetMetadataForSubclassWithTransientBaseClass() : void
    {
        $class = $this->cmf->getMetadataFor(EntitySubClass::class);

        self::assertEmpty($class->getSubClasses());
        self::assertCount(0, $class->getAncestorsIterator());

        self::assertNotNull($class->getProperty('id'));
        self::assertNotNull($class->getProperty('name'));
    }

    public function testGetMetadataForSubclassWithMappedSuperclass() : void
    {
        $class = $this->cmf->getMetadataFor(EntitySubClass2::class);

        self::assertEmpty($class->getSubClasses());
        self::assertCount(0, $class->getAncestorsIterator());

        self::assertNotNull($class->getProperty('id'));
        self::assertNotNull($class->getProperty('name'));
        self::assertNotNull($class->getProperty('mapped1'));
        self::assertNotNull($class->getProperty('mapped2'));

        self::assertTrue($class->isInheritedProperty('mapped1'));
        self::assertTrue($class->isInheritedProperty('mapped2'));

        self::assertNotNull($class->getProperty('transient'));
        self::assertInstanceOf(TransientMetadata::class, $class->getProperty('transient'));

        self::assertArrayHasKey('mappedRelated1', iterator_to_array($class->getDeclaredPropertiesIterator()));
    }

    /**
     * @group DDC-869
     */
    public function testGetMetadataForSubclassWithMappedSuperclassWithRepository() : void
    {
        $class = $this->cmf->getMetadataFor(DDC869CreditCardPayment::class);

        self::assertEquals($class->getCustomRepositoryClassName(), DDC869PaymentRepository::class);

        self::assertNotNull($class->getProperty('id'));
        self::assertNotNull($class->getProperty('value'));
        self::assertNotNull($class->getProperty('creditCardNumber'));

        $class = $this->cmf->getMetadataFor(DDC869ChequePayment::class);

        self::assertEquals($class->getCustomRepositoryClassName(), DDC869PaymentRepository::class);

        self::assertNotNull($class->getProperty('id'));
        self::assertNotNull($class->getProperty('value'));
        self::assertNotNull($class->getProperty('serialNumber'));

        // override repositoryClass
        $class = $this->cmf->getMetadataFor(SubclassWithRepository::class);

        self::assertEquals($class->getCustomRepositoryClassName(), EntityRepository::class);

        self::assertNotNull($class->getProperty('id'));
        self::assertNotNull($class->getProperty('value'));
    }

    /**
     * @group DDC-1203
     */
    public function testUnmappedSuperclassInHierarchy() : void
    {
        $class = $this->cmf->getMetadataFor(HierarchyD::class);

        self::assertNotNull($class->getProperty('id'));
        self::assertNotNull($class->getProperty('a'));
        self::assertNotNull($class->getProperty('d'));
    }

    /**
     * @group DDC-1204
     */
    public function testUnmappedEntityInHierarchy() : void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(
            'Entity \'Doctrine\Tests\ORM\Mapping\HierarchyBEntity\' has to be part of the discriminator map'
            . ' of \'Doctrine\Tests\ORM\Mapping\HierarchyBase\' to be properly mapped in the inheritance hierarchy.'
            . ' Alternatively you can make \'Doctrine\Tests\ORM\Mapping\HierarchyBEntity\' an abstract class to'
            . ' avoid this exception from occurring.'
        );

        $this->cmf->getMetadataFor(HierarchyE::class);
    }

    /**
     * @group DDC-1204
     * @group DDC-1203
     */
    public function testMappedSuperclassWithId() : void
    {
        $class = $this->cmf->getMetadataFor(SuperclassEntity::class);

        self::assertNotNull($class->getProperty('id'));
    }

    /**
     * @group DDC-1156
     * @group DDC-1218
     */
    public function testGeneratedValueFromMappedSuperclass() : void
    {
        /** @var ClassMetadata $class */
        $class = $this->cmf->getMetadataFor(SuperclassEntity::class);

        self::assertSame(GeneratorType::SEQUENCE, $class->getProperty('id')->getValueGenerator()->getType());
        self::assertEquals(
            ['allocationSize' => 1, 'sequenceName' => 'foo'],
            $class->getProperty('id')->getValueGenerator()->getDefinition()
        );
    }

    /**
     * @group DDC-1156
     * @group DDC-1218
     */
    public function testSequenceDefinitionInHierarchyWithSandwichMappedSuperclass() : void
    {
        /** @var ClassMetadata $class */
        $class = $this->cmf->getMetadataFor(HierarchyD::class);

        self::assertSame(GeneratorType::SEQUENCE, $class->getProperty('id')->getValueGenerator()->getType());
        self::assertEquals(
            ['allocationSize' => 1, 'sequenceName' => 'foo'],
            $class->getProperty('id')->getValueGenerator()->getDefinition()
        );
    }

    /**
     * @group DDC-1156
     * @group DDC-1218
     */
    public function testMultipleMappedSuperclasses() : void
    {
        /** @var ClassMetadata $class */
        $class = $this->cmf->getMetadataFor(MediumSuperclassEntity::class);

        self::assertSame(GeneratorType::SEQUENCE, $class->getProperty('id')->getValueGenerator()->getType());
        self::assertEquals(
            ['allocationSize' => 1, 'sequenceName' => 'foo'],
            $class->getProperty('id')->getValueGenerator()->getDefinition()
        );
    }
}

class TransientBaseClass
{
    private $transient1;
    private $transient2;
}

/** @ORM\Entity */
class EntitySubClass extends TransientBaseClass
{
    /** @ORM\Id @ORM\Column(type="integer") */
    private $id;
    /** @ORM\Column(type="string") */
    private $name;
}

/** @ORM\MappedSuperclass */
class MappedSuperclassBase
{
    /** @ORM\Column(type="integer") */
    private $mapped1;
    /** @ORM\Column(type="string") */
    private $mapped2;
    /**
     * @ORM\OneToOne(targetEntity=MappedSuperclassRelated1::class)
     * @ORM\JoinColumn(name="related1_id", referencedColumnName="id")
     */
    private $mappedRelated1;
    private $transient;
}
class MappedSuperclassRelated1
{
}

/** @ORM\Entity */
class EntitySubClass2 extends MappedSuperclassBase
{
    /** @ORM\Id @ORM\Column(type="integer") */
    private $id;
    /** @ORM\Column(type="string") */
    private $name;
}

/**
 * @ORM\MappedSuperclass
 */
class MappedSuperclassBaseIndex
{
    /** @ORM\Column(type="string") */
    private $mapped1;
    /** @ORM\Column(type="string") */
    private $mapped2;
}

/** @ORM\Entity @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="IDX_NAME_INDEX",columns={"name"})}) */
class EntityIndexSubClass extends MappedSuperclassBaseIndex
{
    /** @ORM\Id @ORM\Column(type="integer") */
    private $id;
    /** @ORM\Column(type="string") */
    private $name;
}

/**
 * @ORM\Entity
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string", length=20)
 * @ORM\DiscriminatorMap({
 *     "c"   = HierarchyC::class,
 *     "d"   = HierarchyD::class,
 *     "e"   = HierarchyE::class
 * })
 */
abstract class HierarchyBase
{
    /**
     * @ORM\Column(type="integer") @ORM\Id @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="foo")
     *
     * @var int
     */
    public $id;
}

/** @ORM\MappedSuperclass */
abstract class HierarchyASuperclass extends HierarchyBase
{
    /** @ORM\Column(type="string") */
    public $a;
}

/** @ORM\Entity */
class HierarchyBEntity extends HierarchyBase
{
    /** @ORM\Column(type="string") */
    public $b;
}

/** @ORM\Entity */
class HierarchyC extends HierarchyBase
{
    /** @ORM\Column(type="string") */
    public $c;
}

/** @ORM\Entity */
class HierarchyD extends HierarchyASuperclass
{
    /** @ORM\Column(type="string") */
    public $d;
}

/** @ORM\Entity */
class HierarchyE extends HierarchyBEntity
{
    /** @ORM\Column(type="string") */
    public $e;
}

/** @ORM\Entity */
class SuperclassEntity extends SuperclassBase
{
}

/** @ORM\MappedSuperclass */
abstract class SuperclassBase
{
    /**
     * @ORM\Column(type="integer") @ORM\Id @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="foo")
     */
    public $id;
}

/** @ORM\MappedSuperclass */
abstract class MediumSuperclassBase extends SuperclassBase
{
}

/** @ORM\Entity */
class MediumSuperclassEntity extends MediumSuperclassBase
{
}

/** @ORM\Entity(repositoryClass = "Doctrine\ORM\EntityRepository") */
class SubclassWithRepository extends DDC869Payment
{
}
