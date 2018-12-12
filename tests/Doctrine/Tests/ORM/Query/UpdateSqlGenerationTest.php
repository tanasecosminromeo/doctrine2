<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Query;

use Doctrine\DBAL\Types\Type as DBALType;
use Doctrine\Tests\DbalTypes\NegativeToPositiveType;
use Doctrine\Tests\OrmTestCase;
use Exception;

/**
 * Test case for testing the saving and referencing of query identifiers.
 */
class UpdateSqlGenerationTest extends OrmTestCase
{
    private $em;

    protected function setUp() : void
    {
        if (DBALType::hasType('negative_to_positive')) {
            DBALType::overrideType('negative_to_positive', NegativeToPositiveType::class);
        } else {
            DBALType::addType('negative_to_positive', NegativeToPositiveType::class);
        }

        $this->em = $this->getTestEntityManager();
    }

    public function assertSqlGeneration($dqlToBeTested, $sqlToBeConfirmed)
    {
        try {
            $query        = $this->em->createQuery($dqlToBeTested);
            $sqlGenerated = $query->getSql();

            $query->free();
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }

        self::assertEquals($sqlToBeConfirmed, $sqlGenerated);
    }

    public function testSupportsQueriesWithoutWhere() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.name = ?1',
            'UPDATE "cms_users" SET "name" = ?'
        );
    }

    public function testSupportsMultipleFieldsWithoutWhere() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.name = ?1, u.username = ?2',
            'UPDATE "cms_users" SET "name" = ?, "username" = ?'
        );
    }

    public function testSupportsWhereClauses() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.name = ?1 WHERE u.id = ?2',
            'UPDATE "cms_users" SET "name" = ? WHERE "id" = ?'
        );
    }

    public function testSupportsWhereClausesOnTheUpdatedField() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.name = ?1 WHERE u.name = ?2',
            'UPDATE "cms_users" SET "name" = ? WHERE "name" = ?'
        );
    }

    public function testSupportsMultipleWhereClause() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.name = ?1 WHERE u.name = ?2 AND u.status = ?3',
            'UPDATE "cms_users" SET "name" = ? WHERE "name" = ? AND "status" = ?'
        );
    }

    public function testSupportsInClause() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.name = ?1 WHERE u.id IN (1, 3, 4)',
            'UPDATE "cms_users" SET "name" = ? WHERE "id" IN (1, 3, 4)'
        );
    }

    public function testSupportsParametrizedInClause() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.name = ?1 WHERE u.id IN (?2, ?3, ?4)',
            'UPDATE "cms_users" SET "name" = ? WHERE "id" IN (?, ?, ?)'
        );
    }

    public function testSupportsNotInClause() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.name = ?1 WHERE u.id NOT IN (1, 3, 4)',
            'UPDATE "cms_users" SET "name" = ? WHERE "id" NOT IN (1, 3, 4)'
        );
    }

    public function testSupportsGreaterThanClause() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.status = ?1 WHERE u.id > ?2',
            'UPDATE "cms_users" SET "status" = ? WHERE "id" > ?'
        );
    }

    public function testSupportsGreaterThanOrEqualToClause() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.status = ?1 WHERE u.id >= ?2',
            'UPDATE "cms_users" SET "status" = ? WHERE "id" >= ?'
        );
    }

    public function testSupportsLessThanClause() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.status = ?1 WHERE u.id < ?2',
            'UPDATE "cms_users" SET "status" = ? WHERE "id" < ?'
        );
    }

    public function testSupportsLessThanOrEqualToClause() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.status = ?1 WHERE u.id <= ?2',
            'UPDATE "cms_users" SET "status" = ? WHERE "id" <= ?'
        );
    }

    public function testSupportsBetweenClause() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.status = ?1 WHERE u.id BETWEEN :from AND :to',
            'UPDATE "cms_users" SET "status" = ? WHERE "id" BETWEEN ? AND ?'
        );
    }

    public function testSingleValuedAssociationFieldInWhere() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsPhonenumber p SET p.phonenumber = 1234 WHERE p.user = ?1',
            'UPDATE "cms_phonenumbers" SET "phonenumber" = 1234 WHERE "user_id" = ?'
        );
    }

    public function testSingleValuedAssociationFieldInSetClause() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsComment c SET c.article = null WHERE c.article = ?1',
            'UPDATE "cms_comments" SET "article_id" = NULL WHERE "article_id" = ?'
        );
    }

    /**
     * @group DDC-980
     */
    public function testSubselectTableAliasReferencing() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CMS\CmsUser u SET u.status = \'inactive\' WHERE SIZE(u.groups) = 10',
            'UPDATE "cms_users" SET "status" = \'inactive\' WHERE (SELECT COUNT(*) FROM "cms_users_groups" t0 WHERE t0."user_id" = "cms_users"."id") = 10'
        );
    }

    public function testCustomTypeValueSqlCompletelyIgnoredInUpdateStatements() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\CustomType\CustomTypeParent p SET p.customInteger = 1 WHERE p.id = 1',
            'UPDATE "customtype_parents" SET "customInteger" = 1 WHERE "id" = 1'
        );
    }

    public function testUpdateWithSubselectAsNewValue() : void
    {
        self::assertSqlGeneration(
            'UPDATE Doctrine\Tests\Models\Company\CompanyFixContract fc SET fc.fixPrice = (SELECT ce2.salary FROM Doctrine\Tests\Models\Company\CompanyEmployee ce2 WHERE ce2.id = 2) WHERE fc.id = 1',
            'UPDATE "company_contracts" SET "fixPrice" = (SELECT t0."salary" FROM "company_employees" t0 INNER JOIN "company_persons" t1 ON t0."id" = t1."id" LEFT JOIN "company_managers" t2 ON t0."id" = t2."id" WHERE t1."id" = 2) WHERE ("id" = 1) AND "discr" IN (\'fix\')'
        );
    }
}
