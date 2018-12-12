<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * @group DDC-2575
 */
class DDC2575Test extends OrmFunctionalTestCase
{
    private $rootsEntities = [];
    private $aEntities     = [];
    private $bEntities     = [];

    protected function setUp() : void
    {
        parent::setUp();

        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC2575Root::class),
                $this->em->getClassMetadata(DDC2575A::class),
                $this->em->getClassMetadata(DDC2575B::class),
            ]
        );

        $entityRoot1 = new DDC2575Root(1);
        $entityB1    = new DDC2575B(2);
        $entityA1    = new DDC2575A($entityRoot1, $entityB1);

        $this->em->persist($entityRoot1);
        $this->em->persist($entityA1);
        $this->em->persist($entityB1);

        $entityRoot2 = new DDC2575Root(3);
        $entityB2    = new DDC2575B(4);
        $entityA2    = new DDC2575A($entityRoot2, $entityB2);

        $this->em->persist($entityRoot2);
        $this->em->persist($entityA2);
        $this->em->persist($entityB2);

        $this->em->flush();

        $this->rootsEntities[] = $entityRoot1;
        $this->rootsEntities[] = $entityRoot2;

        $this->aEntities[] = $entityA1;
        $this->aEntities[] = $entityA2;

        $this->bEntities[] = $entityB1;
        $this->bEntities[] = $entityB2;

        $this->em->clear();
    }

    public function testHydrationIssue() : void
    {
        $repository = $this->em->getRepository(DDC2575Root::class);
        $qb         = $repository->createQueryBuilder('r')
            ->select('r, a, b')
            ->leftJoin('r.aRelation', 'a')
            ->leftJoin('a.bRelation', 'b');

        $query  = $qb->getQuery();
        $result = $query->getResult();

        self::assertCount(2, $result);

        $row = $result[0];
        self::assertNotNull($row->aRelation);
        self::assertEquals(1, $row->id);
        self::assertNotNull($row->aRelation->rootRelation);
        self::assertSame($row, $row->aRelation->rootRelation);
        self::assertNotNull($row->aRelation->bRelation);
        self::assertEquals(2, $row->aRelation->bRelation->id);

        $row = $result[1];
        self::assertNotNull($row->aRelation);
        self::assertEquals(3, $row->id);
        self::assertNotNull($row->aRelation->rootRelation);
        self::assertSame($row, $row->aRelation->rootRelation);
        self::assertNotNull($row->aRelation->bRelation);
        self::assertEquals(4, $row->aRelation->bRelation->id);
    }
}

/**
 * @ORM\Entity
 */
class DDC2575Root
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    public $id;

    /** @ORM\Column(type="integer") */
    public $sampleField;

    /** @ORM\OneToOne(targetEntity=DDC2575A::class, mappedBy="rootRelation") */
    public $aRelation;

    public function __construct($id, $value = 0)
    {
        $this->id          = $id;
        $this->sampleField = $value;
    }
}

/**
 * @ORM\Entity
 */
class DDC2575A
{
    /**
     * @ORM\Id
     * @ORM\OneToOne(targetEntity=DDC2575Root::class, inversedBy="aRelation")
     * @ORM\JoinColumn(name="root_id", referencedColumnName="id", nullable=FALSE, onDelete="CASCADE")
     */
    public $rootRelation;

    /**
     * @ORM\ManyToOne(targetEntity=DDC2575B::class)
     * @ORM\JoinColumn(name="b_id", referencedColumnName="id", nullable=FALSE, onDelete="CASCADE")
     */
    public $bRelation;

    public function __construct(DDC2575Root $rootRelation, DDC2575B $bRelation)
    {
        $this->rootRelation = $rootRelation;
        $this->bRelation    = $bRelation;
    }
}

/**
 * @ORM\Entity
 */
class DDC2575B
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    public $id;

    /** @ORM\Column(type="integer") */
    public $sampleField;

    public function __construct($id, $value = 0)
    {
        $this->id          = $id;
        $this->sampleField = $value;
    }
}
