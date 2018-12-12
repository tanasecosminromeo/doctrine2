<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * Functional tests for orphan removal with one to many association.
 */
class DDC3644Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->setUpEntitySchema(
            [
                DDC3644User::class,
                DDC3644Address::class,
                DDC3644Animal::class,
                DDC3644Pet::class,
            ]
        );
    }

    /**
     * @group DDC-3644
     */
    public function testIssueWithRegularEntity() : void
    {
        // Define initial dataset
        $current   = new DDC3644Address('Sao Paulo, SP, Brazil');
        $previous  = new DDC3644Address('Rio de Janeiro, RJ, Brazil');
        $initial   = new DDC3644Address('Sao Carlos, SP, Brazil');
        $addresses = new ArrayCollection([$current, $previous, $initial]);
        $user      = new DDC3644User();

        $user->name = 'Guilherme Blanco';
        $user->setAddresses($addresses);

        $this->em->persist($user);
        $this->em->persist($current);
        $this->em->persist($previous);
        $this->em->persist($initial);

        $this->em->flush();

        $userId = $user->id;
        unset($current, $previous, $initial, $addresses, $user);

        $this->em->clear();

        // Replace entire collection (this should trigger OneToManyPersister::remove())
        $current   = new DDC3644Address('Toronto, ON, Canada');
        $addresses = new ArrayCollection([$current]);
        $user      = $this->em->find(DDC3644User::class, $userId);

        $user->setAddresses($addresses);

        $this->em->persist($user);
        $this->em->persist($current);

        $this->em->flush();
        $this->em->clear();

        // We should only have 1 item in the collection list now
        $user = $this->em->find(DDC3644User::class, $userId);

        self::assertCount(1, $user->addresses);

        // We should only have 1 item in the addresses table too
        $repository = $this->em->getRepository(DDC3644Address::class);
        $addresses  = $repository->findAll();

        self::assertCount(1, $addresses);
    }

    /**
     * @group DDC-3644
     */
    public function testIssueWithJoinedEntity() : void
    {
        // Define initial dataset
        $actual = new DDC3644Pet('Catharina');
        $past   = new DDC3644Pet('Nanny');
        $pets   = new ArrayCollection([$actual, $past]);
        $user   = new DDC3644User();

        $user->name = 'Guilherme Blanco';
        $user->setPets($pets);

        $this->em->persist($user);
        $this->em->persist($actual);
        $this->em->persist($past);

        $this->em->flush();

        $userId = $user->id;
        unset($actual, $past, $pets, $user);

        $this->em->clear();

        // Replace entire collection (this should trigger OneToManyPersister::remove())
        $actual = new DDC3644Pet('Valentina');
        $pets   = new ArrayCollection([$actual]);
        $user   = $this->em->find(DDC3644User::class, $userId);

        $user->setPets($pets);

        $this->em->persist($user);
        $this->em->persist($actual);

        $this->em->flush();
        $this->em->clear();

        // We should only have 1 item in the collection list now
        $user = $this->em->find(DDC3644User::class, $userId);

        self::assertCount(1, $user->pets);

        // We should only have 1 item in the pets table too
        $repository = $this->em->getRepository(DDC3644Pet::class);
        $pets       = $repository->findAll();

        self::assertCount(1, $pets);
    }
}

/**
 * @ORM\Entity
 */
class DDC3644User
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer", name="hash_id")
     */
    public $id;

    /** @ORM\Column(type="string") */
    public $name;

    /** @ORM\OneToMany(targetEntity=DDC3644Address::class, mappedBy="user", orphanRemoval=true) */
    public $addresses = [];

    /** @ORM\OneToMany(targetEntity=DDC3644Pet::class, mappedBy="owner", orphanRemoval=true) */
    public $pets = [];

    public function setAddresses(Collection $addresses)
    {
        $self = $this;

        $this->addresses = $addresses;

        $addresses->map(static function ($address) use ($self) {
            $address->user = $self;
        });
    }

    public function setPets(Collection $pets)
    {
        $self = $this;

        $this->pets = $pets;

        $pets->map(static function ($pet) use ($self) {
            $pet->owner = $self;
        });
    }
}

/**
 * @ORM\Entity
 */
class DDC3644Address
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    public $id;

    /**
     * @ORM\ManyToOne(targetEntity=DDC3644User::class, inversedBy="addresses")
     * @ORM\JoinColumn(referencedColumnName="hash_id")
     */
    public $user;

    /** @ORM\Column(type="string") */
    public $address;

    public function __construct($address)
    {
        $this->address = $address;
    }
}

/**
 * @ORM\Entity
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="discriminator", type="string")
 * @ORM\DiscriminatorMap({"pet" = DDC3644Pet::class})
 */
abstract class DDC3644Animal
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    public $id;

    /** @ORM\Column(type="string") */
    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }
}

/**
 * @ORM\Entity
 */
class DDC3644Pet extends DDC3644Animal
{
    /**
     * @ORM\ManyToOne(targetEntity=DDC3644User::class, inversedBy="pets")
     * @ORM\JoinColumn(referencedColumnName="hash_id")
     */
    public $owner;
}
