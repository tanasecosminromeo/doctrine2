<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;
use function iterator_to_array;

class DDC599Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();
        //$this->em->getConnection()->getConfiguration()->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger);
        try {
            $this->schemaTool->createSchema([
                $this->em->getClassMetadata(DDC599Item::class),
                $this->em->getClassMetadata(DDC599Subitem::class),
                $this->em->getClassMetadata(DDC599Child::class),
            ]);
        } catch (Exception $ignored) {
        }
    }

    public function testCascadeRemoveOnInheritanceHierarchy() : void
    {
        $item          = new DDC599Subitem();
        $item->elem    = 'foo';
        $child         = new DDC599Child();
        $child->parent = $item;
        $item->getChildren()->add($child);
        $this->em->persist($item);
        $this->em->persist($child);
        $this->em->flush();
        $this->em->clear();

        $item = $this->em->find(DDC599Item::class, $item->id);

        $this->em->remove($item);
        $this->em->flush(); // Should not fail

        self::assertFalse($this->em->contains($item));
        $children = $item->getChildren();
        self::assertFalse($this->em->contains($children[0]));

        $this->em->clear();

        $item2       = new DDC599Subitem();
        $item2->elem = 'bar';
        $this->em->persist($item2);
        $this->em->flush();

        $child2         = new DDC599Child();
        $child2->parent = $item2;
        $item2->getChildren()->add($child2);
        $this->em->persist($child2);
        $this->em->flush();

        $this->em->remove($item2);
        $this->em->flush(); // should not fail

        self::assertFalse($this->em->contains($item));
        $children = $item->getChildren();
        self::assertFalse($this->em->contains($children[0]));
    }

    public function testCascadeRemoveOnChildren() : void
    {
        $class = $this->em->getClassMetadata(DDC599Subitem::class);

        self::assertArrayHasKey('children', iterator_to_array($class->getDeclaredPropertiesIterator()));
        self::assertContains('remove', $class->getProperty('children')->getCascade());
    }
}

/**
 * @ORM\Entity
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="integer")
 * @ORM\DiscriminatorMap({"0" = DDC599Item::class, "1" = DDC599Subitem::class})
 */
class DDC599Item
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /** @ORM\OneToMany(targetEntity=DDC599Child::class, mappedBy="parent", cascade={"remove"}) */
    protected $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getChildren()
    {
        return $this->children;
    }
}

/**
 * @ORM\Entity
 */
class DDC599Subitem extends DDC599Item
{
    /** @ORM\Column(type="string") */
    public $elem;
}

/**
 * @ORM\Entity
 */
class DDC599Child
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @ORM\ManyToOne(targetEntity=DDC599Item::class, inversedBy="children")
     * @ORM\JoinColumn(name="parentId", referencedColumnName="id")
     */
    public $parent;
}
