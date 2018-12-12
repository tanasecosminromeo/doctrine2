<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;
use function get_class;

/**
 * @group DDC-2996
 */
class DDC2996Test extends OrmFunctionalTestCase
{
    public function testIssue() : void
    {
        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC2996User::class),
                $this->em->getClassMetadata(DDC2996UserPreference::class),
            ]
        );

        $pref        = new DDC2996UserPreference();
        $pref->user  = new DDC2996User();
        $pref->value = 'foo';

        $this->em->persist($pref);
        $this->em->persist($pref->user);
        $this->em->flush();

        $pref->value = 'bar';
        $this->em->flush();

        self::assertEquals(1, $pref->user->counter);

        $this->em->clear();

        $pref = $this->em->find(DDC2996UserPreference::class, $pref->id);
        self::assertEquals(1, $pref->user->counter);
    }
}

/**
 * @ORM\Entity
 */
class DDC2996User
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    public $id;
    /** @ORM\Column(type="integer") */
    public $counter = 0;
}

/**
 * @ORM\Entity @ORM\HasLifecycleCallbacks
 */
class DDC2996UserPreference
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    public $id;
    /** @ORM\Column(type="string") */
    public $value;

    /** @ORM\ManyToOne(targetEntity=DDC2996User::class) */
    public $user;

    /**
     * @ORM\PreFlush
     */
    public function preFlush($event)
    {
        $em  = $event->getEntityManager();
        $uow = $em->getUnitOfWork();

        if ($uow->getOriginalEntityData($this->user)) {
            $this->user->counter++;
            $uow->recomputeSingleEntityChangeSet(
                $em->getClassMetadata(get_class($this->user)),
                $this->user
            );
        }
    }
}
