<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\DBAL\Schema\Sequence;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;

class SequenceEmulatedIdentityStrategyTest extends OrmFunctionalTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp() : void
    {
        parent::setUp();

        if (! $this->em->getConnection()->getDatabasePlatform()->usesSequenceEmulatedIdentityColumns()) {
            $this->markTestSkipped(
                'This test is special to platforms emulating IDENTITY key generation strategy through sequences.'
            );
        } else {
            try {
                $this->schemaTool->createSchema(
                    [$this->em->getClassMetadata(SequenceEmulatedIdentityEntity::class)]
                );
            } catch (Exception $e) {
                // Swallow all exceptions. We do not test the schema tool here.
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown() : void
    {
        parent::tearDown();

        $connection = $this->em->getConnection();
        $platform   = $connection->getDatabasePlatform();

        // drop sequence manually due to dependency
        $connection->exec(
            $platform->getDropSequenceSQL(
                new Sequence($platform->getIdentitySequenceName('seq_identity', 'id'))
            )
        );
    }

    public function testPreSavePostSaveCallbacksAreInvoked() : void
    {
        $entity = new SequenceEmulatedIdentityEntity();
        $entity->setValue('hello');
        $this->em->persist($entity);
        $this->em->flush();
        self::assertInternalType('numeric', $entity->getId());
        self::assertGreaterThan(0, $entity->getId());
        self::assertTrue($this->em->contains($entity));
    }
}

/** @ORM\Entity @ORM\Table(name="seq_identity") */
class SequenceEmulatedIdentityEntity
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue(strategy="IDENTITY") */
    private $id;

    /** @ORM\Column(type="string") */
    private $value;

    public function getId()
    {
        return $this->id;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }
}
