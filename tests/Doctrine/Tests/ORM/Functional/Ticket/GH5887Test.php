<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * @group 5887
 */
class GH5887Test extends OrmFunctionalTestCase
{
    public function setUp() : void
    {
        parent::setUp();

        Type::addType(GH5887CustomIdObjectType::NAME, GH5887CustomIdObjectType::class);

        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(GH5887Cart::class),
                $this->em->getClassMetadata(GH5887Customer::class),
            ]
        );

        $this->markTestIncomplete('Requires updates to SqlWalker');
    }

    public function testLazyLoadsForeignEntitiesInOneToOneRelationWhileHavingCustomIdObject() : void
    {
        $customerId = new GH5887CustomIdObject(1);
        $customer   = new GH5887Customer();
        $customer->setId($customerId);

        $cartId = 2;
        $cart   = new GH5887Cart();
        $cart->setId($cartId);
        $cart->setCustomer($customer);

        $this->em->persist($customer);
        $this->em->persist($cart);
        $this->em->flush();
        $this->em->clear();

        $customerRepository = $this->em->getRepository(GH5887Customer::class);
        /** @var GH5887Customer $customer */
        $customer = $customerRepository->createQueryBuilder('c')
            ->where('c.id = :id')
            ->setParameter('id', $customerId->getId())
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(GH5887Cart::class, $customer->getCart());
    }
}

/**
 * @ORM\Entity
 */
class GH5887Cart
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="NONE")
     *
     * @var int
     */
    private $id;

    /**
     * One Cart has One Customer.
     *
     * @ORM\OneToOne(targetEntity=GH5887Customer::class, inversedBy="cart")
     * @ORM\JoinColumn(name="customer_id", referencedColumnName="id")
     *
     * @var GH5887Customer
     */
    private $customer;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return GH5887Customer
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    public function setCustomer(GH5887Customer $customer)
    {
        if ($this->customer !== $customer) {
            $this->customer = $customer;
            $customer->setCart($this);
        }
    }
}

/**
 * @ORM\Entity
 */
class GH5887Customer
{
    /**
     * @ORM\Id
     * @ORM\Column(type="GH5887CustomIdObject")
     * @ORM\GeneratedValue(strategy="NONE")
     *
     * @var GH5887CustomIdObject
     */
    private $id;

    /**
     * One Customer has One Cart.
     *
     * @ORM\OneToOne(targetEntity=GH5887Cart::class, mappedBy="customer")
     *
     * @var GH5887Cart
     */
    private $cart;

    /**
     * @return GH5887CustomIdObject
     */
    public function getId()
    {
        return $this->id;
    }

    public function setId(GH5887CustomIdObject $id)
    {
        $this->id = $id;
    }

    public function getCart() : GH5887Cart
    {
        return $this->cart;
    }

    public function setCart(GH5887Cart $cart)
    {
        if ($this->cart !== $cart) {
            $this->cart = $cart;
            $cart->setCustomer($this);
        }
    }
}

class GH5887CustomIdObject
{
    /** @var int */
    private $id;

    /**
     * @param int $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function __toString()
    {
        return 'non existing id';
    }
}

class GH5887CustomIdObjectType extends StringType
{
    public const NAME = 'GH5887CustomIdObject';

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return $value->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return new GH5887CustomIdObject($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }
}
