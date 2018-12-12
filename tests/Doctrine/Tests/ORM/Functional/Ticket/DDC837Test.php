<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

class DDC837Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();
        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC837Super::class),
                $this->em->getClassMetadata(DDC837Class1::class),
                $this->em->getClassMetadata(DDC837Class2::class),
                $this->em->getClassMetadata(DDC837Class3::class),
                $this->em->getClassMetadata(DDC837Aggregate::class),
            ]
        );
    }

    /**
     * @group DDC-837
     */
    public function testIssue() : void
    {
        //$this->em->getConnection()->getConfiguration()->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger);

        $c1              = new DDC837Class1();
        $c1->title       = 'Foo';
        $c1->description = 'Foo';
        $aggregate1      = new DDC837Aggregate('test1');
        $c1->aggregate   = $aggregate1;

        $c2              = new DDC837Class2();
        $c2->title       = 'Bar';
        $c2->description = 'Bar';
        $c2->text        = 'Bar';
        $aggregate2      = new DDC837Aggregate('test2');
        $c2->aggregate   = $aggregate2;

        $c3          = new DDC837Class3();
        $c3->apples  = 'Baz';
        $c3->bananas = 'Baz';

        $this->em->persist($c1);
        $this->em->persist($aggregate1);
        $this->em->persist($c2);
        $this->em->persist($aggregate2);
        $this->em->persist($c3);
        $this->em->flush();
        $this->em->clear();

        // Test Class1
        $e1 = $this->em->find(DDC837Super::class, $c1->id);

        self::assertInstanceOf(DDC837Class1::class, $e1);
        self::assertEquals('Foo', $e1->title);
        self::assertEquals('Foo', $e1->description);
        self::assertInstanceOf(DDC837Aggregate::class, $e1->aggregate);
        self::assertEquals('test1', $e1->aggregate->getSysname());

        // Test Class 2
        $e2 = $this->em->find(DDC837Super::class, $c2->id);

        self::assertInstanceOf(DDC837Class2::class, $e2);
        self::assertEquals('Bar', $e2->title);
        self::assertEquals('Bar', $e2->description);
        self::assertEquals('Bar', $e2->text);
        self::assertInstanceOf(DDC837Aggregate::class, $e2->aggregate);
        self::assertEquals('test2', $e2->aggregate->getSysname());

        $all = $this->em->getRepository(DDC837Super::class)->findAll();

        foreach ($all as $obj) {
            if ($obj instanceof DDC837Class1) {
                self::assertEquals('Foo', $obj->title);
                self::assertEquals('Foo', $obj->description);
            } elseif ($obj instanceof DDC837Class2) {
                self::assertSame($e2, $obj);
                self::assertEquals('Bar', $obj->title);
                self::assertEquals('Bar', $obj->description);
                self::assertEquals('Bar', $obj->text);
            } elseif ($obj instanceof DDC837Class3) {
                self::assertEquals('Baz', $obj->apples);
                self::assertEquals('Baz', $obj->bananas);
            } else {
                $this->fail('Instance of DDC837Class1, DDC837Class2 or DDC837Class3 expected.');
            }
        }
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="DDC837Super")
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({"class1" = DDC837Class1::class, "class2" = DDC837Class2::class, "class3"=DDC837Class3::class})
 */
abstract class DDC837Super
{
    /**
     * @ORM\Id @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;
}

/**
 * @ORM\Entity
 */
class DDC837Class1 extends DDC837Super
{
    /** @ORM\Column(name="title", type="string", length=150) */
    public $title;

    /** @ORM\Column(name="content", type="string", length=500) */
    public $description;

    /** @ORM\OneToOne(targetEntity=DDC837Aggregate::class) */
    public $aggregate;
}

/**
 * @ORM\Entity
 */
class DDC837Class2 extends DDC837Super
{
    /** @ORM\Column(name="title", type="string", length=150) */
    public $title;

    /** @ORM\Column(name="content", type="string", length=500) */
    public $description;

    /** @ORM\Column(name="text", type="text") */
    public $text;

    /** @ORM\OneToOne(targetEntity=DDC837Aggregate::class) */
    public $aggregate;
}

/**
 * An extra class to demonstrate why title and description aren't in Super
 *
 * @ORM\Entity
 */
class DDC837Class3 extends DDC837Super
{
    /** @ORM\Column(name="title", type="string", length=150) */
    public $apples;

    /** @ORM\Column(name="content", type="string", length=500) */
    public $bananas;
}

/**
 * @ORM\Entity
 */
class DDC837Aggregate
{
    /**
     * @ORM\Id @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue
     */
    public $id;

    /** @ORM\Column(name="sysname", type="string") */
    protected $sysname;

    public function __construct($sysname)
    {
        $this->sysname = $sysname;
    }

    public function getSysname()
    {
        return $this->sysname;
    }
}
