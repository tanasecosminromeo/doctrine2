<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;
use function explode;
use function get_class;
use function implode;
use function is_array;
use function strtolower;

/**
 * @group DDC-2012
 * @group non-cacheable
 */
class DDC2012Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        Type::addType(DDC2012TsVectorType::MYTYPE, DDC2012TsVectorType::class);

        DDC2012TsVectorType::$calls = [];

        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC2012Item::class),
                $this->em->getClassMetadata(DDC2012ItemPerson::class),
            ]
        );
    }

    public function testIssue() : void
    {
        $item      = new DDC2012ItemPerson();
        $item->tsv = ['word1', 'word2', 'word3'];

        $this->em->persist($item);
        $this->em->flush();
        $this->em->clear();

        $item = $this->em->find(get_class($item), $item->id);

        self::assertArrayHasKey('convertToDatabaseValueSQL', DDC2012TsVectorType::$calls);
        self::assertArrayHasKey('convertToDatabaseValue', DDC2012TsVectorType::$calls);
        self::assertArrayHasKey('convertToPHPValue', DDC2012TsVectorType::$calls);

        self::assertCount(1, DDC2012TsVectorType::$calls['convertToDatabaseValueSQL']);
        self::assertCount(1, DDC2012TsVectorType::$calls['convertToDatabaseValue']);
        self::assertCount(1, DDC2012TsVectorType::$calls['convertToPHPValue']);

        self::assertInstanceOf(DDC2012Item::class, $item);
        self::assertEquals(['word1', 'word2', 'word3'], $item->tsv);

        $item->tsv = ['word1', 'word2'];

        $this->em->persist($item);
        $this->em->flush();
        $this->em->clear();

        $item = $this->em->find(get_class($item), $item->id);

        self::assertCount(2, DDC2012TsVectorType::$calls['convertToDatabaseValueSQL']);
        self::assertCount(2, DDC2012TsVectorType::$calls['convertToDatabaseValue']);
        self::assertCount(2, DDC2012TsVectorType::$calls['convertToPHPValue']);

        self::assertInstanceOf(DDC2012Item::class, $item);
        self::assertEquals(['word1', 'word2'], $item->tsv);
    }
}

/**
 * @ORM\Table(name="ddc2010_item")
 * @ORM\Entity
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="type_id", type="smallint")
 * @ORM\DiscriminatorMap({
 *      1 = DDC2012ItemPerson::class,
 *      2 = DDC2012Item::class
 * })
 */
class DDC2012Item
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    public $id;

    /** @ORM\Column(name="tsv", type="tsvector", nullable=true) */
    public $tsv;
}

/**
 * @ORM\Table(name="ddc2010_item_person")
 * @ORM\Entity
 */
class DDC2012ItemPerson extends DDC2012Item
{
}

class DDC2012TsVectorType extends Type
{
    public const MYTYPE = 'tsvector';

    public static $calls = [];

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return $platform->getVarcharTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (is_array($value)) {
            $value = implode(' ', $value);
        }

        self::$calls[__FUNCTION__][] = [
            'value'     => $value,
            'platform'  => $platform,
        ];

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        self::$calls[__FUNCTION__][] = [
            'value'     => $value,
            'platform'  => $platform,
        ];

        return explode(' ', strtolower($value));
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        self::$calls[__FUNCTION__][] = [
            'sqlExpr'   => $sqlExpr,
            'platform'  => $platform,
        ];

        // changed to upper expression to keep the test compatible with other Databases
        //sprintf('to_tsvector(%s)', $sqlExpr);

        return $platform->getUpperExpression($sqlExpr);
    }

    /**
     * {@inheritdoc}
     */
    public function canRequireSQLConversion()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::MYTYPE;
    }
}
