<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * @group DDC-1719
 */
class DDC1719Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC1719SimpleEntity::class),
            ]
        );
    }

    protected function tearDown() : void
    {
        parent::tearDown();

        $this->schemaTool->dropSchema(
            [
                $this->em->getClassMetadata(DDC1719SimpleEntity::class),
            ]
        );
    }

    public function testCreateRetrieveUpdateDelete() : void
    {
        $e1 = new DDC1719SimpleEntity('Bar 1');
        $e2 = new DDC1719SimpleEntity('Foo 1');

        // Create
        $this->em->persist($e1);
        $this->em->persist($e2);
        $this->em->flush();
        $this->em->clear();

        $e1Id = $e1->id;
        $e2Id = $e2->id;

        // Retrieve
        $e1 = $this->em->find(DDC1719SimpleEntity::class, $e1Id);
        $e2 = $this->em->find(DDC1719SimpleEntity::class, $e2Id);

        self::assertInstanceOf(DDC1719SimpleEntity::class, $e1);
        self::assertInstanceOf(DDC1719SimpleEntity::class, $e2);

        self::assertEquals($e1Id, $e1->id);
        self::assertEquals($e2Id, $e2->id);

        self::assertEquals('Bar 1', $e1->value);
        self::assertEquals('Foo 1', $e2->value);

        $e1->value = 'Bar 2';
        $e2->value = 'Foo 2';

        // Update
        $this->em->persist($e1);
        $this->em->persist($e2);
        $this->em->flush();

        self::assertEquals('Bar 2', $e1->value);
        self::assertEquals('Foo 2', $e2->value);

        self::assertInstanceOf(DDC1719SimpleEntity::class, $e1);
        self::assertInstanceOf(DDC1719SimpleEntity::class, $e2);

        self::assertEquals($e1Id, $e1->id);
        self::assertEquals($e2Id, $e2->id);

        self::assertEquals('Bar 2', $e1->value);
        self::assertEquals('Foo 2', $e2->value);

        // Delete
        $this->em->remove($e1);
        $this->em->remove($e2);
        $this->em->flush();

        $e1 = $this->em->find(DDC1719SimpleEntity::class, $e1Id);
        $e2 = $this->em->find(DDC1719SimpleEntity::class, $e2Id);

        self::assertNull($e1);
        self::assertNull($e2);
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="ddc-1719-simple-entity")
 */
class DDC1719SimpleEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", name="simple-entity-id")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /** @ORM\Column(type="string", name="simple-entity-value") */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }
}
