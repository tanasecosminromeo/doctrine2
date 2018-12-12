<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

class DDC211Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();
        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC211User::class),
                $this->em->getClassMetadata(DDC211Group::class),
            ]
        );
    }

    public function testIssue() : void
    {
        //$this->em->getConnection()->getConfiguration()->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger);

        $user = new DDC211User();
        $user->setName('John Doe');

        $this->em->persist($user);
        $this->em->flush();

        $groupNames = ['group 1', 'group 2', 'group 3', 'group 4'];
        foreach ($groupNames as $name) {
            $group = new DDC211Group();
            $group->setName($name);
            $this->em->persist($group);
            $this->em->flush();

            if (! $user->getGroups()->contains($group)) {
                $user->getGroups()->add($group);
                $group->getUsers()->add($user);
                $this->em->flush();
            }
        }

        self::assertEquals(4, $user->getGroups()->count());
    }
}


/**
 * @ORM\Entity
 * @ORM\Table(name="ddc211_users")
 */
class DDC211User
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /** @ORM\Column(name="name", type="string") */
    protected $name;

    /**
     * @ORM\ManyToMany(targetEntity=DDC211Group::class, inversedBy="users")
     *   @ORM\JoinTable(name="user_groups",
     *       joinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="id")},
     *       inverseJoinColumns={@ORM\JoinColumn(name="group_id", referencedColumnName="id")}
     *   )
     */
    protected $groups;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getGroups()
    {
        return $this->groups;
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="ddc211_groups")
 */
class DDC211Group
{
    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /** @ORM\Column(name="name", type="string") */
    protected $name;

    /** @ORM\ManyToMany(targetEntity=DDC211User::class, mappedBy="groups") */
    protected $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getUsers()
    {
        return $this->users;
    }
}
