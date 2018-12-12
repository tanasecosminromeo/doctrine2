<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Locking;

use DateTime;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;
use function date;
use function strtotime;

class OptimisticTest extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        try {
            $this->schemaTool->createSchema(
                [
                    $this->em->getClassMetadata(OptimisticJoinedParent::class),
                    $this->em->getClassMetadata(OptimisticJoinedChild::class),
                    $this->em->getClassMetadata(OptimisticStandard::class),
                    $this->em->getClassMetadata(OptimisticTimestamp::class),
                ]
            );
        } catch (Exception $e) {
            // Swallow all exceptions. We do not test the schema tool here.
        }

        $this->conn = $this->em->getConnection();
    }

    public function testJoinedChildInsertSetsInitialVersionValue() : OptimisticJoinedChild
    {
        $test = new OptimisticJoinedChild();

        $test->name     = 'child';
        $test->whatever = 'whatever';

        $this->em->persist($test);
        $this->em->flush();

        self::assertEquals(1, $test->version);

        return $test;
    }

    /**
     * @depends testJoinedChildInsertSetsInitialVersionValue
     */
    public function testJoinedChildFailureThrowsException(OptimisticJoinedChild $child) : void
    {
        $q = $this->em->createQuery('SELECT t FROM Doctrine\Tests\ORM\Functional\Locking\OptimisticJoinedChild t WHERE t.id = :id');

        $q->setParameter('id', $child->id);

        $test = $q->getSingleResult();

        // Manually update/increment the version so we can try and save the same
        // $test and make sure the exception is thrown saying the record was
        // changed or updated since you read it
        $this->conn->executeQuery('UPDATE optimistic_joined_parent SET version = ? WHERE id = ?', [2, $test->id]);

        // Now lets change a property and try and save it again
        $test->whatever = 'ok';

        try {
            $this->em->flush();
        } catch (OptimisticLockException $e) {
            self::assertSame($test, $e->getEntity());
        }
    }

    public function testJoinedParentInsertSetsInitialVersionValue() : OptimisticJoinedParent
    {
        $test = new OptimisticJoinedParent();

        $test->name = 'parent';

        $this->em->persist($test);
        $this->em->flush();

        self::assertEquals(1, $test->version);

        return $test;
    }

    /**
     * @depends testJoinedParentInsertSetsInitialVersionValue
     */
    public function testJoinedParentFailureThrowsException(OptimisticJoinedParent $parent) : void
    {
        $q = $this->em->createQuery('SELECT t FROM Doctrine\Tests\ORM\Functional\Locking\OptimisticJoinedParent t WHERE t.id = :id');

        $q->setParameter('id', $parent->id);

        $test = $q->getSingleResult();

        // Manually update/increment the version so we can try and save the same
        // $test and make sure the exception is thrown saying the record was
        // changed or updated since you read it
        $this->conn->executeQuery('UPDATE optimistic_joined_parent SET version = ? WHERE id = ?', [2, $test->id]);

        // Now lets change a property and try and save it again
        $test->name = 'WHATT???';

        try {
            $this->em->flush();
        } catch (OptimisticLockException $e) {
            self::assertSame($test, $e->getEntity());
        }
    }

    public function testMultipleFlushesDoIncrementalUpdates() : void
    {
        $test = new OptimisticStandard();

        for ($i = 0; $i < 5; $i++) {
            $test->name = 'test' . $i;

            $this->em->persist($test);
            $this->em->flush();

            self::assertInternalType('int', $test->getVersion());
            self::assertEquals($i + 1, $test->getVersion());
        }
    }

    public function testStandardInsertSetsInitialVersionValue() : OptimisticStandard
    {
        $test = new OptimisticStandard();

        $test->name = 'test';

        $this->em->persist($test);
        $this->em->flush();

        self::assertInternalType('int', $test->getVersion());
        self::assertEquals(1, $test->getVersion());

        return $test;
    }

    /**
     * @depends testStandardInsertSetsInitialVersionValue
     */
    public function testStandardFailureThrowsException(OptimisticStandard $entity) : void
    {
        $q = $this->em->createQuery('SELECT t FROM Doctrine\Tests\ORM\Functional\Locking\OptimisticStandard t WHERE t.id = :id');

        $q->setParameter('id', $entity->id);

        $test = $q->getSingleResult();

        // Manually update/increment the version so we can try and save the same
        // $test and make sure the exception is thrown saying the record was
        // changed or updated since you read it
        $this->conn->executeQuery('UPDATE optimistic_standard SET version = ? WHERE id = ?', [2, $test->id]);

        // Now lets change a property and try and save it again
        $test->name = 'WHATT???';

        try {
            $this->em->flush();
        } catch (OptimisticLockException $e) {
            self::assertSame($test, $e->getEntity());
        }
    }

    public function testLockWorksWithProxy() : void
    {
        $test       = new OptimisticStandard();
        $test->name = 'test';

        $this->em->persist($test);
        $this->em->flush();
        $this->em->clear();

        $proxy = $this->em->getReference(OptimisticStandard::class, $test->id);

        $this->em->lock($proxy, LockMode::OPTIMISTIC, 1);

        self::addToAssertionCount(1);
    }

    public function testOptimisticTimestampSetsDefaultValue() : OptimisticTimestamp
    {
        $test = new OptimisticTimestamp();

        $test->name = 'Testing';

        self::assertNull($test->version, 'Pre-Condition');

        $this->em->persist($test);
        $this->em->flush();

        self::assertInstanceOf('DateTime', $test->version);

        return $test;
    }

    /**
     * @depends testOptimisticTimestampSetsDefaultValue
     */
    public function testOptimisticTimestampFailureThrowsException(OptimisticTimestamp $entity) : void
    {
        $q = $this->em->createQuery('SELECT t FROM Doctrine\Tests\ORM\Functional\Locking\OptimisticTimestamp t WHERE t.id = :id');

        $q->setParameter('id', $entity->id);

        $test = $q->getSingleResult();

        self::assertInstanceOf('DateTime', $test->version);

        // Manually increment the version datetime column
        $format = $this->em->getConnection()->getDatabasePlatform()->getDateTimeFormatString();

        $this->conn->executeQuery('UPDATE optimistic_timestamp SET version = ? WHERE id = ?', [date($format, strtotime($test->version->format($format)) + 3600), $test->id]);

        // Try and update the record and it should throw an exception
        $caughtException = null;
        $test->name      = 'Testing again';

        try {
            $this->em->flush();
        } catch (OptimisticLockException $e) {
            $caughtException = $e;
        }

        self::assertNotNull($caughtException, 'No OptimisticLockingException was thrown');
        self::assertSame($test, $caughtException->getEntity());
    }

    /**
     * @depends testOptimisticTimestampSetsDefaultValue
     */
    public function testOptimisticTimestampLockFailureThrowsException(OptimisticTimestamp $entity) : void
    {
        $q = $this->em->createQuery('SELECT t FROM Doctrine\Tests\ORM\Functional\Locking\OptimisticTimestamp t WHERE t.id = :id');

        $q->setParameter('id', $entity->id);

        $test = $q->getSingleResult();

        self::assertInstanceOf('DateTime', $test->version);

        // Try to lock the record with an older timestamp and it should throw an exception
        $caughtException = null;

        try {
            $expectedVersionExpired = DateTime::createFromFormat('U', (string) ($test->version->getTimestamp()-3600));

            $this->em->lock($test, LockMode::OPTIMISTIC, $expectedVersionExpired);
        } catch (OptimisticLockException $e) {
            $caughtException = $e;
        }

        self::assertNotNull($caughtException, 'No OptimisticLockingException was thrown');
        self::assertSame($test, $caughtException->getEntity());
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="optimistic_joined_parent")
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({"parent" = OptimisticJoinedParent::class, "child" = OptimisticJoinedChild::class})
 */
class OptimisticJoinedParent
{
    /**
     * @ORM\Id @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /** @ORM\Column(type="string", length=255) */
    public $name;

    /** @ORM\Version @ORM\Column(type="integer") */
    public $version;
}

/**
 * @ORM\Entity
 * @ORM\Table(name="optimistic_joined_child")
 */
class OptimisticJoinedChild extends OptimisticJoinedParent
{
    /** @ORM\Column(type="string", length=255) */
    public $whatever;
}

/**
 * @ORM\Entity
 * @ORM\Table(name="optimistic_standard")
 */
class OptimisticStandard
{
    /**
     * @ORM\Id @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /** @ORM\Column(type="string", length=255) */
    public $name;

    /** @ORM\Version @ORM\Column(type="integer") */
    private $version;

    public function getVersion()
    {
        return $this->version;
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="optimistic_timestamp")
 */
class OptimisticTimestamp
{
    /**
     * @ORM\Id @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /** @ORM\Column(type="string", length=255) */
    public $name;

    /** @ORM\Version @ORM\Column(type="datetime") */
    public $version;
}
