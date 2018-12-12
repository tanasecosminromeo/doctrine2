<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional;

use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Tests\Models\Company\CompanyAuction;
use Doctrine\Tests\Models\Company\CompanyEmployee;
use Doctrine\Tests\Models\Company\CompanyEvent;
use Doctrine\Tests\Models\Company\CompanyManager;
use Doctrine\Tests\Models\Company\CompanyOrganization;
use Doctrine\Tests\Models\Company\CompanyPerson;
use Doctrine\Tests\Models\Company\CompanyRaffle;
use Doctrine\Tests\OrmFunctionalTestCase;
use ProxyManager\Proxy\GhostObjectInterface;
use function get_class;
use function sprintf;

/**
 * Functional tests for the Class Table Inheritance mapping strategy.
 */
class ClassTableInheritanceTest extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        $this->useModelSet('company');

        parent::setUp();
    }

    public function testCRUD() : void
    {
        $person = new CompanyPerson();
        $person->setName('Roman S. Borschel');

        $this->em->persist($person);

        $employee = new CompanyEmployee();
        $employee->setName('Roman S. Borschel');
        $employee->setSalary(100000);
        $employee->setDepartment('IT');

        $this->em->persist($employee);

        $employee->setName('Guilherme Blanco');
        $this->em->flush();

        $this->em->clear();

        $query = $this->em->createQuery('select p from ' . CompanyPerson::class . ' p order by p.name desc');

        $entities = $query->getResult();

        self::assertCount(2, $entities);
        self::assertInstanceOf(CompanyPerson::class, $entities[0]);
        self::assertInstanceOf(CompanyEmployee::class, $entities[1]);
        self::assertInternalType('numeric', $entities[0]->getId());
        self::assertInternalType('numeric', $entities[1]->getId());
        self::assertEquals('Roman S. Borschel', $entities[0]->getName());
        self::assertEquals('Guilherme Blanco', $entities[1]->getName());
        self::assertEquals(100000, $entities[1]->getSalary());

        $this->em->clear();

        $query = $this->em->createQuery('select p from ' . CompanyEmployee::class . ' p');

        $entities = $query->getResult();

        self::assertCount(1, $entities);
        self::assertInstanceOf(CompanyEmployee::class, $entities[0]);
        self::assertInternalType('numeric', $entities[0]->getId());
        self::assertEquals('Guilherme Blanco', $entities[0]->getName());
        self::assertEquals(100000, $entities[0]->getSalary());

        $this->em->clear();

        $guilherme = $this->em->getRepository(get_class($employee))->findOneBy(['name' => 'Guilherme Blanco']);

        self::assertInstanceOf(CompanyEmployee::class, $guilherme);
        self::assertEquals('Guilherme Blanco', $guilherme->getName());

        $this->em->clear();

        $query = $this->em->createQuery('update ' . CompanyEmployee::class . " p set p.name = ?1, p.department = ?2 where p.name='Guilherme Blanco' and p.salary = ?3");

        $query->setParameter(1, 'NewName', 'string');
        $query->setParameter(2, 'NewDepartment');
        $query->setParameter(3, 100000);
        $query->getSQL();

        $numUpdated = $query->execute();

        self::assertEquals(1, $numUpdated);

        $query = $this->em->createQuery('delete from ' . CompanyPerson::class . ' p');

        $numDeleted = $query->execute();

        self::assertEquals(2, $numDeleted);
    }

    public function testMultiLevelUpdateAndFind() : void
    {
        $manager = new CompanyManager();
        $manager->setName('Roman S. Borschel');
        $manager->setSalary(100000);
        $manager->setDepartment('IT');
        $manager->setTitle('CTO');
        $this->em->persist($manager);
        $this->em->flush();

        $manager->setName('Roman B.');
        $manager->setSalary(119000);
        $manager->setTitle('CEO');
        $this->em->persist($manager);
        $this->em->flush();

        $this->em->clear();

        $manager = $this->em->find(CompanyManager::class, $manager->getId());

        self::assertInstanceOf(CompanyManager::class, $manager);
        self::assertEquals('Roman B.', $manager->getName());
        self::assertEquals(119000, $manager->getSalary());
        self::assertEquals('CEO', $manager->getTitle());
        self::assertInternalType('numeric', $manager->getId());
    }

    public function testFindOnBaseClass() : void
    {
        $manager = new CompanyManager();
        $manager->setName('Roman S. Borschel');
        $manager->setSalary(100000);
        $manager->setDepartment('IT');
        $manager->setTitle('CTO');
        $this->em->persist($manager);
        $this->em->flush();

        $this->em->clear();

        $person = $this->em->find(CompanyPerson::class, $manager->getId());

        self::assertInstanceOf(CompanyManager::class, $person);
        self::assertEquals('Roman S. Borschel', $person->getName());
        self::assertEquals(100000, $person->getSalary());
        self::assertEquals('CTO', $person->getTitle());
        self::assertInternalType('numeric', $person->getId());
    }

    public function testSelfReferencingOneToOne() : void
    {
        $manager = new CompanyManager();
        $manager->setName('John Smith');
        $manager->setSalary(100000);
        $manager->setDepartment('IT');
        $manager->setTitle('CTO');

        $wife = new CompanyPerson();
        $wife->setName('Mary Smith');
        $wife->setSpouse($manager);

        self::assertSame($manager, $wife->getSpouse());
        self::assertSame($wife, $manager->getSpouse());

        $this->em->persist($manager);
        $this->em->persist($wife);
        $this->em->flush();
        $this->em->clear();

        $query = $this->em->createQuery('select p, s from ' . CompanyPerson::class . ' p join p.spouse s where p.name=\'Mary Smith\'');

        $result = $query->getResult();

        self::assertCount(1, $result);
        self::assertInstanceOf(CompanyPerson::class, $result[0]);
        self::assertEquals('Mary Smith', $result[0]->getName());
        self::assertInstanceOf(CompanyEmployee::class, $result[0]->getSpouse());
        self::assertEquals('John Smith', $result[0]->getSpouse()->getName());
        self::assertSame($result[0], $result[0]->getSpouse()->getSpouse());
    }

    public function testSelfReferencingManyToMany() : void
    {
        $person1 = new CompanyPerson();
        $person1->setName('Roman');

        $person2 = new CompanyPerson();
        $person2->setName('Jonathan');

        $person1->addFriend($person2);

        self::assertCount(1, $person1->getFriends());
        self::assertCount(1, $person2->getFriends());

        $this->em->persist($person1);
        $this->em->persist($person2);

        $this->em->flush();

        $this->em->clear();

        $query = $this->em->createQuery('select p, f from ' . CompanyPerson::class . ' p join p.friends f where p.name=?1');

        $query->setParameter(1, 'Roman');

        $result = $query->getResult();

        self::assertCount(1, $result);
        self::assertCount(1, $result[0]->getFriends());
        self::assertEquals('Roman', $result[0]->getName());

        $friends = $result[0]->getFriends();

        self::assertEquals('Jonathan', $friends[0]->getName());
    }

    public function testLazyLoading1() : void
    {
        $org    = new CompanyOrganization();
        $event1 = new CompanyAuction();
        $event1->setData('auction');
        $org->addEvent($event1);
        $event2 = new CompanyRaffle();
        $event2->setData('raffle');
        $org->addEvent($event2);

        $this->em->persist($org);
        $this->em->flush();
        $this->em->clear();

        $orgId = $org->getId();

        $q = $this->em->createQuery('select a from Doctrine\Tests\Models\Company\CompanyOrganization a where a.id = ?1');
        $q->setParameter(1, $orgId);

        $result = $q->getResult();

        self::assertCount(1, $result);
        self::assertInstanceOf(CompanyOrganization::class, $result[0]);
        self::assertNull($result[0]->getMainEvent());

        $events = $result[0]->getEvents();

        self::assertInstanceOf(PersistentCollection::class, $events);
        self::assertFalse($events->isInitialized());

        self::assertCount(2, $events);

        if ($events[0] instanceof CompanyAuction) {
            self::assertInstanceOf(CompanyRaffle::class, $events[1]);
        } else {
            self::assertInstanceOf(CompanyRaffle::class, $events[0]);
            self::assertInstanceOf(CompanyAuction::class, $events[1]);
        }
    }

    public function testLazyLoading2() : void
    {
        $org    = new CompanyOrganization();
        $event1 = new CompanyAuction();
        $event1->setData('auction');
        $org->setMainEvent($event1);

        $this->em->persist($org);
        $this->em->flush();
        $this->em->clear();

        $q = $this->em->createQuery('select a from ' . CompanyEvent::class . ' a where a.id = ?1');

        $q->setParameter(1, $event1->getId());

        $result = $q->getResult();

        self::assertCount(1, $result);
        self::assertInstanceOf(CompanyAuction::class, $result[0], sprintf('Is of class %s', get_class($result[0])));

        $this->em->clear();

        $q = $this->em->createQuery('select a from ' . CompanyOrganization::class . ' a where a.id = ?1');

        $q->setParameter(1, $org->getId());

        $result = $q->getResult();

        self::assertCount(1, $result);
        self::assertInstanceOf(CompanyOrganization::class, $result[0]);

        $mainEvent = $result[0]->getMainEvent();

        // mainEvent should have been loaded because it can't be lazy
        self::assertInstanceOf(CompanyAuction::class, $mainEvent);
        self::assertNotInstanceOf(GhostObjectInterface::class, $mainEvent);
    }

    /**
     * @group DDC-368
     */
    public function testBulkUpdateIssueDDC368() : void
    {
        $this->em->createQuery('UPDATE ' . CompanyEmployee::class . ' AS p SET p.salary = 1')
                  ->execute();

        $result = $this->em->createQuery('SELECT count(p.id) FROM ' . CompanyEmployee::class . ' p WHERE p.salary = 1')
                            ->getResult();

        self::assertGreaterThan(0, $result);
    }

    /**
     * @group DDC-1341
     */
    public function testBulkUpdateNonScalarParameterDDC1341() : void
    {
        $this->em->createQuery('UPDATE ' . CompanyEmployee::class . ' AS p SET p.startDate = ?0 WHERE p.department = ?1')
                  ->setParameter(0, new DateTime())
                  ->setParameter(1, 'IT')
                  ->execute();

        self::addToAssertionCount(1);
    }

    /**
     * @group DDC-130
     */
    public function testDeleteJoinTableRecords() : void
    {
        $employee1 = new CompanyEmployee();
        $employee1->setName('gblanco');
        $employee1->setSalary(0);
        $employee1->setDepartment('IT');

        $employee2 = new CompanyEmployee();
        $employee2->setName('jwage');
        $employee2->setSalary(0);
        $employee2->setDepartment('IT');

        $employee1->addFriend($employee2);

        $this->em->persist($employee1);
        $this->em->persist($employee2);
        $this->em->flush();

        $employee1Id = $employee1->getId();

        $this->em->remove($employee1);
        $this->em->flush();

        self::assertNull($this->em->find(get_class($employee1), $employee1Id));
    }

    /**
     * @group DDC-728
     */
    public function testQueryForInheritedSingleValuedAssociation() : void
    {
        $manager = new CompanyManager();
        $manager->setName('gblanco');
        $manager->setSalary(1234);
        $manager->setTitle('Awesome!');
        $manager->setDepartment('IT');

        $person = new CompanyPerson();
        $person->setName('spouse');

        $manager->setSpouse($person);

        $this->em->persist($manager);
        $this->em->persist($person);
        $this->em->flush();
        $this->em->clear();

        $dqlManager = $this->em->createQuery('SELECT m FROM ' . CompanyManager::class . ' m WHERE m.spouse = ?1')
                                ->setParameter(1, $person->getId())
                                ->getSingleResult();

        self::assertEquals($manager->getId(), $dqlManager->getId());
        self::assertEquals($person->getId(), $dqlManager->getSpouse()->getId());
    }

    /**
     * @group DDC-817
     */
    public function testFindByAssociation() : void
    {
        $manager = new CompanyManager();
        $manager->setName('gblanco');
        $manager->setSalary(1234);
        $manager->setTitle('Awesome!');
        $manager->setDepartment('IT');

        $person = new CompanyPerson();
        $person->setName('spouse');

        $manager->setSpouse($person);

        $this->em->persist($manager);
        $this->em->persist($person);
        $this->em->flush();
        $this->em->clear();

        $repos    = $this->em->getRepository(CompanyManager::class);
        $pmanager = $repos->findOneBy(['spouse' => $person->getId()]);

        self::assertEquals($manager->getId(), $pmanager->getId());

        $repos    = $this->em->getRepository(CompanyPerson::class);
        $pmanager = $repos->findOneBy(['spouse' => $person->getId()]);

        self::assertEquals($manager->getId(), $pmanager->getId());
    }

    /**
     * @group DDC-834
     */
    public function testGetReferenceEntityWithSubclasses() : void
    {
        $manager = new CompanyManager();
        $manager->setName('gblanco');
        $manager->setSalary(1234);
        $manager->setTitle('Awesome!');
        $manager->setDepartment('IT');

        $this->em->persist($manager);
        $this->em->flush();
        $this->em->clear();

        $ref = $this->em->getReference(CompanyPerson::class, $manager->getId());
        self::assertNotInstanceOf(GhostObjectInterface::class, $ref, 'Cannot Request a proxy from a class that has subclasses.');
        self::assertInstanceOf(CompanyPerson::class, $ref);
        self::assertInstanceOf(CompanyEmployee::class, $ref, 'Direct fetch of the reference has to load the child class Employee directly.');
        $this->em->clear();

        $ref = $this->em->getReference(CompanyManager::class, $manager->getId());
        self::assertInstanceOf(GhostObjectInterface::class, $ref, 'A proxy can be generated only if no subclasses exists for the requested reference.');
    }

    /**
     * @group DDC-992
     */
    public function testGetSubClassManyToManyCollection() : void
    {
        $manager = new CompanyManager();
        $manager->setName('gblanco');
        $manager->setSalary(1234);
        $manager->setTitle('Awesome!');
        $manager->setDepartment('IT');

        $person = new CompanyPerson();
        $person->setName('friend');

        $manager->addFriend($person);

        $this->em->persist($manager);
        $this->em->persist($person);
        $this->em->flush();
        $this->em->clear();

        $manager = $this->em->find(CompanyManager::class, $manager->getId());

        self::assertCount(1, $manager->getFriends());
    }

    /**
     * @group DDC-1777
     */
    public function testExistsSubclass() : void
    {
        $manager = new CompanyManager();
        $manager->setName('gblanco');
        $manager->setSalary(1234);
        $manager->setTitle('Awesome!');
        $manager->setDepartment('IT');

        self::assertFalse($this->em->getUnitOfWork()->getEntityPersister(get_class($manager))->exists($manager));

        $this->em->persist($manager);
        $this->em->flush();

        self::assertTrue($this->em->getUnitOfWork()->getEntityPersister(get_class($manager))->exists($manager));
    }

    /**
     * @group DDC-1637
     */
    public function testMatching() : void
    {
        $manager = new CompanyManager();
        $manager->setName('gblanco');
        $manager->setSalary(1234);
        $manager->setTitle('Awesome!');
        $manager->setDepartment('IT');

        $this->em->persist($manager);
        $this->em->flush();

        $repository = $this->em->getRepository(CompanyEmployee::class);
        $users      = $repository->matching(new Criteria(
            Criteria::expr()->eq('department', 'IT')
        ));

        self::assertCount(1, $users);

        $repository = $this->em->getRepository(CompanyManager::class);
        $users      = $repository->matching(new Criteria(
            Criteria::expr()->eq('department', 'IT')
        ));

        self::assertCount(1, $users);
    }
}
