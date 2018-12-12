<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use DateTime;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;
use function get_class;

/**
 * Class DDC2895Test
 */
class DDC2895Test extends OrmFunctionalTestCase
{
    public function setUp() : void
    {
        parent::setUp();
        try {
            $this->schemaTool->createSchema(
                [
                    $this->em->getClassMetadata(DDC2895::class),
                ]
            );
        } catch (Exception $e) {
        }
    }

    public function testPostLoadOneToManyInheritance() : void
    {
        $cm = $this->em->getClassMetadata(DDC2895::class);

        self::assertEquals(
            [
                'prePersist' => ['setLastModifiedPreUpdate'],
                'preUpdate' => ['setLastModifiedPreUpdate'],
            ],
            $cm->lifecycleCallbacks
        );

        $ddc2895 = new DDC2895();

        $this->em->persist($ddc2895);
        $this->em->flush();
        $this->em->clear();

        /** @var DDC2895 $ddc2895 */
        $ddc2895 = $this->em->find(get_class($ddc2895), $ddc2895->id);

        self::assertNotNull($ddc2895->getLastModified());
    }
}

/**
 * @ORM\MappedSuperclass
 * @ORM\HasLifecycleCallbacks
 */
abstract class AbstractDDC2895
{
    /**
     * @ORM\Column(name="last_modified", type="datetimetz", nullable=false)
     *
     * @var DateTime
     */
    protected $lastModified;

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function setLastModifiedPreUpdate()
    {
        $this->setLastModified(new DateTime());
    }

    /**
     * @param DateTime $lastModified
     */
    public function setLastModified($lastModified)
    {
        $this->lastModified = $lastModified;
    }

    /**
     * @return DateTime
     */
    public function getLastModified()
    {
        return $this->lastModified;
    }
}

/**
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class DDC2895 extends AbstractDDC2895
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    public $id;

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}
