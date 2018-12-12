<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;
use ProxyManager\Proxy\GhostObjectInterface;
use function get_class;

class DDC237Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();
        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC237EntityX::class),
                $this->em->getClassMetadata(DDC237EntityY::class),
                $this->em->getClassMetadata(DDC237EntityZ::class),
            ]
        );
    }

    public function testUninitializedProxyIsInitializedOnFetchJoin() : void
    {
        $x = new DDC237EntityX();
        $y = new DDC237EntityY();
        $z = new DDC237EntityZ();

        $x->data = 'X';
        $y->data = 'Y';
        $z->data = 'Z';

        $x->y = $y;
        $z->y = $y;

        $this->em->persist($x);
        $this->em->persist($y);
        $this->em->persist($z);

        $this->em->flush();
        $this->em->clear();

        $x2 = $this->em->find(get_class($x), $x->id); // proxy injected for Y
        self::assertInstanceOf(GhostObjectInterface::class, $x2->y);
        self::assertFalse($x2->y->isProxyInitialized());

        // proxy for Y is in identity map

        $z2 = $this->em->createQuery('select z,y from ' . get_class($z) . ' z join z.y y where z.id = ?1')
                ->setParameter(1, $z->id)
                ->getSingleResult();
        self::assertInstanceOf(GhostObjectInterface::class, $z2->y);
        self::assertTrue($z2->y->isProxyInitialized());
        self::assertEquals('Y', $z2->y->data);
        self::assertEquals($y->id, $z2->y->id);

        // since the Y is the same, the instance from the identity map is
        // used, even if it is a proxy.

        self::assertNotSame($x, $x2);
        self::assertNotSame($z, $z2);
        self::assertSame($z2->y, $x2->y);
        self::assertInstanceOf(GhostObjectInterface::class, $z2->y);
    }
}


/**
 * @ORM\Entity @ORM\Table(name="ddc237_x")
 */
class DDC237EntityX
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;
    /** @ORM\Column(type="string") */
    public $data;
    /**
     * @ORM\OneToOne(targetEntity=DDC237EntityY::class)
     * @ORM\JoinColumn(name="y_id", referencedColumnName="id")
     */
    public $y;
}


/** @ORM\Entity @ORM\Table(name="ddc237_y") */
class DDC237EntityY
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;
    /** @ORM\Column(type="string") */
    public $data;
}

/** @ORM\Entity @ORM\Table(name="ddc237_z") */
class DDC237EntityZ
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;
    /** @ORM\Column(type="string") */
    public $data;

    /**
     * @ORM\OneToOne(targetEntity=DDC237EntityY::class)
     * @ORM\JoinColumn(name="y_id", referencedColumnName="id")
     */
    public $y;
}
