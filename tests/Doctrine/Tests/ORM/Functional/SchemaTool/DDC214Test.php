<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\SchemaTool;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\Tests\Models;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;
use const PHP_EOL;
use function array_filter;
use function implode;
use function strpos;

/**
 * WARNING: This test should be run as last test! It can affect others very easily!
 */
class DDC214Test extends OrmFunctionalTestCase
{
    private $classes = [];

    public function setUp() : void
    {
        parent::setUp();

        $conn = $this->em->getConnection();

        if (strpos($conn->getDriver()->getName(), 'sqlite') !== false) {
            $this->markTestSkipped('SQLite does not support ALTER TABLE statements.');
        }
    }

    /**
     * @group DDC-214
     */
    public function testCmsAddressModel() : void
    {
        $this->classes = [
            Models\CMS\CmsUser::class,
            Models\CMS\CmsPhonenumber::class,
            Models\CMS\CmsAddress::class,
            Models\CMS\CmsGroup::class,
            Models\CMS\CmsArticle::class,
            Models\CMS\CmsEmail::class,
        ];

        self::assertCreatedSchemaNeedsNoUpdates($this->classes);
    }

    /**
     * @group DDC-214
     */
    public function testCompanyModel() : void
    {
        $this->classes = [
            Models\Company\CompanyPerson::class,
            Models\Company\CompanyEmployee::class,
            Models\Company\CompanyManager::class,
            Models\Company\CompanyOrganization::class,
            Models\Company\CompanyEvent::class,
            Models\Company\CompanyAuction::class,
            Models\Company\CompanyRaffle::class,
            Models\Company\CompanyCar::class,
        ];

        self::assertCreatedSchemaNeedsNoUpdates($this->classes);
    }

    public function assertCreatedSchemaNeedsNoUpdates($classes)
    {
        $classMetadata = [];
        foreach ($classes as $class) {
            $classMetadata[] = $this->em->getClassMetadata($class);
        }

        try {
            $this->schemaTool->createSchema($classMetadata);
        } catch (Exception $e) {
            // was already created
        }

        $sm = $this->em->getConnection()->getSchemaManager();

        $fromSchema = $sm->createSchema();
        $toSchema   = $this->schemaTool->getSchemaFromMetadata($classMetadata);

        $comparator = new Comparator();
        $schemaDiff = $comparator->compare($fromSchema, $toSchema);

        $sql = $schemaDiff->toSql($this->em->getConnection()->getDatabasePlatform());
        $sql = array_filter($sql, static function ($sql) {
            return strpos($sql, 'DROP') === false;
        });

        self::assertCount(0, $sql, 'SQL: ' . implode(PHP_EOL, $sql));
    }
}
