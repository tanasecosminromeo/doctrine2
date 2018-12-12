<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\DBAL\Logging\EchoSQLLogger;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;

class DDC832Test extends OrmFunctionalTestCase
{
    public function setUp() : void
    {
        parent::setUp();

        $platform = $this->em->getConnection()->getDatabasePlatform();

        if ($platform->getName() === 'oracle') {
            $this->markTestSkipped('Doesnt run on Oracle.');
        }

        $this->em->getConfiguration()->setSQLLogger(new EchoSQLLogger());

        try {
            $this->schemaTool->createSchema(
                [
                    $this->em->getClassMetadata(DDC832JoinedIndex::class),
                    $this->em->getClassMetadata(DDC832JoinedTreeIndex::class),
                    $this->em->getClassMetadata(DDC832Like::class),
                ]
            );
        } catch (Exception $e) {
        }
    }

    public function tearDown() : void
    {
        /** @var AbstractSchemaManager $sm */
        $platform = $this->em->getConnection()->getDatabasePlatform();

        $sm = $this->em->getConnection()->getSchemaManager();

        $sm->dropTable($platform->quoteIdentifier('TREE_INDEX'));
        $sm->dropTable($platform->quoteIdentifier('INDEX'));
        $sm->dropTable($platform->quoteIdentifier('LIKE'));
    }

    /**
     * @group DDC-832
     */
    public function testQuotedTableBasicUpdate() : void
    {
        $like = new DDC832Like('test');
        $this->em->persist($like);
        $this->em->flush();

        $like->word = 'test2';
        $this->em->flush();
        $this->em->clear();

        self::assertEquals($like, $this->em->find(DDC832Like::class, $like->id));
    }

    /**
     * @group DDC-832
     */
    public function testQuotedTableBasicRemove() : void
    {
        $like = new DDC832Like('test');
        $this->em->persist($like);
        $this->em->flush();

        $idToBeRemoved = $like->id;

        $this->em->remove($like);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->em->find(DDC832Like::class, $idToBeRemoved));
    }

    /**
     * @group DDC-832
     */
    public function testQuotedTableJoinedUpdate() : void
    {
        $index = new DDC832JoinedIndex('test');
        $this->em->persist($index);
        $this->em->flush();

        $index->name = 'asdf';
        $this->em->flush();
        $this->em->clear();

        self::assertEquals($index, $this->em->find(DDC832JoinedIndex::class, $index->id));
    }

    /**
     * @group DDC-832
     */
    public function testQuotedTableJoinedRemove() : void
    {
        $index = new DDC832JoinedIndex('test');
        $this->em->persist($index);
        $this->em->flush();

        $idToBeRemoved = $index->id;

        $this->em->remove($index);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->em->find(DDC832JoinedIndex::class, $idToBeRemoved));
    }

    /**
     * @group DDC-832
     */
    public function testQuotedTableJoinedChildUpdate() : void
    {
        $index = new DDC832JoinedTreeIndex('test', 1, 2);
        $this->em->persist($index);
        $this->em->flush();

        $index->name = 'asdf';
        $this->em->flush();
        $this->em->clear();

        self::assertEquals($index, $this->em->find(DDC832JoinedTreeIndex::class, $index->id));
    }

    /**
     * @group DDC-832
     */
    public function testQuotedTableJoinedChildRemove() : void
    {
        $index = new DDC832JoinedTreeIndex('test', 1, 2);
        $this->em->persist($index);
        $this->em->flush();

        $idToBeRemoved = $index->id;

        $this->em->remove($index);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->em->find(DDC832JoinedTreeIndex::class, $idToBeRemoved));
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="LIKE")
 */
class DDC832Like
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;

    /** @ORM\Column(type="string") */
    public $word;

    /**
     * @ORM\Version
     * @ORM\Column(type="integer")
     */
    public $version;

    public function __construct($word)
    {
        $this->word = $word;
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="INDEX")
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({"like" = DDC832JoinedIndex::class, "fuzzy" = DDC832JoinedTreeIndex::class})
 */
class DDC832JoinedIndex
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;

    /** @ORM\Column(type="string") */
    public $name;

    /**
     * @ORM\Version
     * @ORM\Column(type="integer")
     */
    public $version;

    public function __construct($name)
    {
        $this->name = $name;
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="TREE_INDEX")
 */
class DDC832JoinedTreeIndex extends DDC832JoinedIndex
{
    /** @ORM\Column(type="integer") */
    public $lft;

    /** @ORM\Column(type="integer") */
    public $rgt;

    public function __construct($name, $lft, $rgt)
    {
        $this->name = $name;
        $this->lft  = $lft;
        $this->rgt  = $rgt;
    }
}
