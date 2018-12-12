<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;
use function array_search;

/**
 * @group DDC-2494
 * @group non-cacheable
 */
class DDC3192Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        if (Type::hasType('ddc3192_currency_code')) {
            $this->fail(
                'Type ddc3192_currency_code exists for testing DDC-3192 only, ' .
                'but it has already been registered for some reason'
            );
        }

        Type::addType('ddc3192_currency_code', DDC3192CurrencyCode::class);

        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC3192Currency::class),
                $this->em->getClassMetadata(DDC3192Transaction::class),
            ]
        );
    }

    public function testIssue() : void
    {
        $currency = new DDC3192Currency('BYR');

        $this->em->persist($currency);
        $this->em->flush();

        $amount      = 50;
        $transaction = new DDC3192Transaction($amount, $currency);

        $this->em->persist($transaction);
        $this->em->flush();
        $this->em->close();

        $resultByPersister = $this->em->find(DDC3192Transaction::class, $transaction->id);

        // This works: DDC2494 makes persister set type mapping info to ResultSetMapping
        self::assertEquals('BYR', $resultByPersister->currency->code);

        $this->em->close();

        $query = $this->em->createQuery();
        $query->setDQL('SELECT t FROM ' . DDC3192Transaction::class . ' t WHERE t.id = ?1');
        $query->setParameter(1, $transaction->id);

        $resultByQuery = $query->getSingleResult();

        // This is fixed here: before the fix it used to return 974.
        // because unlike the BasicEntityPersister, SQLWalker doesn't set type info
        self::assertEquals('BYR', $resultByQuery->currency->code);
    }
}

/**
 * @ORM\Table(name="ddc3192_currency")
 * @ORM\Entity
 */
class DDC3192Currency
{
    /**
     * @ORM\Id
     * @ORM\Column(type="ddc3192_currency_code")
     */
    public $code;

    /**
     * @ORM\OneToMany(targetEntity=DDC3192Transaction::class, mappedBy="currency")
     *
     * @var Collection
     */
    public $transactions;

    public function __construct($code)
    {
        $this->code = $code;
    }
}

/**
 * @ORM\Table(name="ddc3192_transaction")
 * @ORM\Entity
 */
class DDC3192Transaction
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    public $id;

    /**
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    public $amount;

    /**
     * @ORM\ManyToOne(targetEntity=DDC3192Currency::class, inversedBy="transactions")
     * @ORM\JoinColumn(name="currency_id", referencedColumnName="code", nullable=false)
     *
     * @var \Doctrine\Tests\ORM\Functional\Ticket\DDC3192Currency
     */
    public $currency;

    public function __construct($amount, DDC3192Currency $currency)
    {
        $this->amount   = $amount;
        $this->currency = $currency;
    }
}

class DDC3192CurrencyCode extends Type
{
    private static $map = ['BYR' => 974];

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getSmallIntTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return self::$map[$value];
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return array_search((int) $value, self::$map, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'ddc3192_currency_code';
    }
}
