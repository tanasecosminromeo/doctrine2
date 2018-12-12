<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * @group DDC-2346
 */
class DDC2346Test extends OrmFunctionalTestCase
{
    /** @var DebugStack */
    protected $logger;

    /**
     * {@inheritDoc}
     */
    protected function setUp() : void
    {
        parent::setUp();

        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC2346Foo::class),
                $this->em->getClassMetadata(DDC2346Bar::class),
                $this->em->getClassMetadata(DDC2346Baz::class),
            ]
        );

        $this->logger = new DebugStack();
    }

    /**
     * Verifies that fetching a OneToMany association with fetch="EAGER" does not cause N+1 queries
     */
    public function testIssue() : void
    {
        $foo1 = new DDC2346Foo();
        $foo2 = new DDC2346Foo();

        $baz1 = new DDC2346Baz();
        $baz2 = new DDC2346Baz();

        $baz1->foo = $foo1;
        $baz2->foo = $foo2;

        $foo1->bars[] = $baz1;
        $foo1->bars[] = $baz2;

        $this->em->persist($foo1);
        $this->em->persist($foo2);
        $this->em->persist($baz1);
        $this->em->persist($baz2);

        $this->em->flush();
        $this->em->clear();

        $this->em->getConnection()->getConfiguration()->setSQLLogger($this->logger);

        $fetchedBazs = $this->em->getRepository(DDC2346Baz::class)->findAll();

        self::assertCount(2, $fetchedBazs);
        self::assertCount(2, $this->logger->queries, 'The total number of executed queries is 2, and not n+1');
    }
}

/** @ORM\Entity */
class DDC2346Foo
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;

    /**
     * @ORM\OneToMany(targetEntity=DDC2346Bar::class, mappedBy="foo")
     *
     * @var DDC2346Bar[]|Collection
     */
    public $bars;

    /** Constructor */
    public function __construct()
    {
        $this->bars = new ArrayCollection();
    }
}

/**
 * @ORM\Entity
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({"bar" = DDC2346Bar::class, "baz" = DDC2346Baz::class})
 */
class DDC2346Bar
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;

    /** @ORM\ManyToOne(targetEntity=DDC2346Foo::class, inversedBy="bars", fetch="EAGER") */
    public $foo;
}


/**
 * @ORM\Entity
 */
class DDC2346Baz extends DDC2346Bar
{
}
