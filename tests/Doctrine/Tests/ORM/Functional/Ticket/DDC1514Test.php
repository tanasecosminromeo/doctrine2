<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;

/**
 * @group DDC-1514
 */
class DDC1514Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        try {
            $this->schemaTool->createSchema(
                [
                    $this->em->getClassMetadata(DDC1514EntityA::class),
                    $this->em->getClassMetadata(DDC1514EntityB::class),
                    $this->em->getClassMetadata(DDC1514EntityC::class),
                ]
            );
        } catch (Exception $ignored) {
        }
    }

    public function testIssue() : void
    {
        $a1        = new DDC1514EntityA();
        $a1->title = '1foo';

        $a2        = new DDC1514EntityA();
        $a2->title = '2bar';

        $b1              = new DDC1514EntityB();
        $b1->entityAFrom = $a1;
        $b1->entityATo   = $a2;

        $b2              = new DDC1514EntityB();
        $b2->entityAFrom = $a2;
        $b2->entityATo   = $a1;

        $c           = new DDC1514EntityC();
        $c->title    = 'baz';
        $a2->entityC = $c;

        $this->em->persist($a1);
        $this->em->persist($a2);
        $this->em->persist($b1);
        $this->em->persist($b2);
        $this->em->persist($c);
        $this->em->flush();
        $this->em->clear();

        $dql     = 'SELECT a, b, ba, c FROM ' . __NAMESPACE__ . '\DDC1514EntityA AS a LEFT JOIN a.entitiesB AS b LEFT JOIN b.entityATo AS ba LEFT JOIN a.entityC AS c ORDER BY a.title';
        $results = $this->em->createQuery($dql)->getResult();

        self::assertEquals($a1->id, $results[0]->id);
        self::assertNull($results[0]->entityC);

        self::assertEquals($a2->id, $results[1]->id);
        self::assertEquals($c->title, $results[1]->entityC->title);
    }
}

/**
 * @ORM\Entity
 */
class DDC1514EntityA
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;
    /** @ORM\Column */
    public $title;
    /** @ORM\ManyToMany(targetEntity=DDC1514EntityB::class, mappedBy="entityAFrom") */
    public $entitiesB;
    /** @ORM\ManyToOne(targetEntity=DDC1514EntityC::class) */
    public $entityC;

    public function __construct()
    {
        $this->entitiesB = new ArrayCollection();
    }
}

/**
 * @ORM\Entity
 */
class DDC1514EntityB
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;

    /** @ORM\ManyToOne(targetEntity=DDC1514EntityA::class, inversedBy="entitiesB") */
    public $entityAFrom;
    /** @ORM\ManyToOne(targetEntity=DDC1514EntityA::class) */
    public $entityATo;
}

/**
 * @ORM\Entity
 */
class DDC1514EntityC
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;
    /** @ORM\Column */
    public $title;
}
