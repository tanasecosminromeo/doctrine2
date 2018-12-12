<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\ORM\Mapping\FetchMode;
use Doctrine\Tests\Models\CMS\CmsArticle;
use Doctrine\Tests\Models\CMS\CmsGroup;
use Doctrine\Tests\Models\CMS\CmsPhonenumber;
use Doctrine\Tests\Models\CMS\CmsUser;
use Doctrine\Tests\Models\DDC2504\DDC2504ChildClass;
use Doctrine\Tests\Models\DDC2504\DDC2504OtherClass;
use Doctrine\Tests\Models\Tweet\Tweet;
use Doctrine\Tests\Models\Tweet\User;
use Doctrine\Tests\Models\Tweet\UserList;
use Doctrine\Tests\OrmFunctionalTestCase;
use function array_shift;

/**
 * Description of ExtraLazyCollectionTest
 */
class ExtraLazyCollectionTest extends OrmFunctionalTestCase
{
    private $userId;
    private $userId2;
    private $groupId;
    private $articleId;
    private $ddc2504OtherClassId;
    private $ddc2504ChildClassId;

    private $username;
    private $groupname;
    private $topic;
    private $phonenumber;

    public function setUp() : void
    {
        $this->useModelSet('tweet');
        $this->useModelSet('cms');
        $this->useModelSet('ddc2504');
        parent::setUp();

        $class = $this->em->getClassMetadata(CmsUser::class);

        $class->getProperty('groups')->setFetchMode(FetchMode::EXTRA_LAZY);
        $class->getProperty('articles')->setFetchMode(FetchMode::EXTRA_LAZY);
        $class->getProperty('phonenumbers')->setFetchMode(FetchMode::EXTRA_LAZY);

        $class->getProperty('groups')->setIndexedBy('name');
        $class->getProperty('articles')->setIndexedBy('topic');
        $class->getProperty('phonenumbers')->setIndexedBy('phonenumber');

        $class->getProperty('groups')->setCache(null);
        $class->getProperty('articles')->setCache(null);
        $class->getProperty('phonenumbers')->setCache(null);

        $class = $this->em->getClassMetadata(CmsGroup::class);

        $class->getProperty('users')->setFetchMode(FetchMode::EXTRA_LAZY);

        $class->getProperty('users')->setIndexedBy('username');

        $this->loadFixture();
    }

    public function tearDown() : void
    {
        parent::tearDown();

        $class = $this->em->getClassMetadata(CmsUser::class);

        $class->getProperty('groups')->setFetchMode(FetchMode::LAZY);
        $class->getProperty('articles')->setFetchMode(FetchMode::LAZY);
        $class->getProperty('phonenumbers')->setFetchMode(FetchMode::LAZY);

        $class->getProperty('groups')->setIndexedBy(null);
        $class->getProperty('articles')->setIndexedBy(null);
        $class->getProperty('phonenumbers')->setIndexedBy(null);

        $class = $this->em->getClassMetadata(CmsGroup::class);

        $class->getProperty('users')->setFetchMode(FetchMode::LAZY);

        $class->getProperty('users')->setIndexedBy(null);
    }

    /**
     * @group DDC-546
     * @group non-cacheable
     */
    public function testCountNotInitializesCollection() : void
    {
        $user       = $this->em->find(CmsUser::class, $this->userId);
        $queryCount = $this->getCurrentQueryCount();

        self::assertFalse($user->groups->isInitialized());
        self::assertCount(3, $user->groups);
        self::assertFalse($user->groups->isInitialized());

        foreach ($user->groups as $group) {
        }

        self::assertEquals($queryCount + 2, $this->getCurrentQueryCount(), 'Expecting two queries to be fired for count, then iteration.');
    }

    /**
     * @group DDC-546
     */
    public function testCountWhenNewEntityPresent() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);

        $newGroup       = new CmsGroup();
        $newGroup->name = 'Test4';

        $user->addGroup($newGroup);
        $this->em->persist($newGroup);

        self::assertFalse($user->groups->isInitialized());
        self::assertCount(4, $user->groups);
        self::assertFalse($user->groups->isInitialized());
    }

    /**
     * @group DDC-546
     * @group non-cacheable
     */
    public function testCountWhenInitialized() : void
    {
        $user       = $this->em->find(CmsUser::class, $this->userId);
        $queryCount = $this->getCurrentQueryCount();

        foreach ($user->groups as $group) {
        }

        self::assertTrue($user->groups->isInitialized());
        self::assertCount(3, $user->groups);
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount(), 'Should only execute one query to initialize collection, no extra query for count() more.');
    }

    /**
     * @group DDC-546
     */
    public function testCountInverseCollection() : void
    {
        $group = $this->em->find(CmsGroup::class, $this->groupId);
        self::assertFalse($group->users->isInitialized(), 'Pre-Condition');

        self::assertCount(4, $group->users);
        self::assertFalse($group->users->isInitialized(), 'Extra Lazy collection should not be initialized by counting the collection.');
    }

    /**
     * @group DDC-546
     */
    public function testCountOneToMany() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        self::assertFalse($user->groups->isInitialized(), 'Pre-Condition');

        self::assertCount(2, $user->articles);
    }

    /**
     * @group DDC-2504
     */
    public function testCountOneToManyJoinedInheritance() : void
    {
        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);

        self::assertFalse($otherClass->childClasses->isInitialized(), 'Pre-Condition');
        self::assertCount(2, $otherClass->childClasses);
    }

    /**
     * @group DDC-546
     */
    public function testFullSlice() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        self::assertFalse($user->groups->isInitialized(), 'Pre-Condition: Collection is not initialized.');

        $someGroups = $user->groups->slice(null);
        self::assertCount(3, $someGroups);
    }

    /**
     * @group DDC-546
     * @group non-cacheable
     */
    public function testSlice() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        self::assertFalse($user->groups->isInitialized(), 'Pre-Condition: Collection is not initialized.');

        $queryCount = $this->getCurrentQueryCount();

        $someGroups = $user->groups->slice(0, 2);

        self::assertContainsOnly(CmsGroup::class, $someGroups);
        self::assertCount(2, $someGroups);
        self::assertFalse($user->groups->isInitialized(), "Slice should not initialize the collection if it wasn't before!");

        $otherGroup = $user->groups->slice(2, 1);

        self::assertContainsOnly(CmsGroup::class, $otherGroup);
        self::assertCount(1, $otherGroup);
        self::assertFalse($user->groups->isInitialized());

        foreach ($user->groups as $group) {
        }

        self::assertTrue($user->groups->isInitialized());
        self::assertCount(3, $user->groups);

        self::assertEquals($queryCount + 3, $this->getCurrentQueryCount());
    }

    /**
     * @group DDC-546
     * @group non-cacheable
     */
    public function testSliceInitializedCollection() : void
    {
        $user       = $this->em->find(CmsUser::class, $this->userId);
        $queryCount = $this->getCurrentQueryCount();

        foreach ($user->groups as $group) {
        }

        $someGroups = $user->groups->slice(0, 2);

        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        self::assertCount(2, $someGroups);
        self::assertTrue($user->groups->contains(array_shift($someGroups)));
        self::assertTrue($user->groups->contains(array_shift($someGroups)));
    }

    /**
     * @group DDC-546
     */
    public function testSliceInverseCollection() : void
    {
        $group = $this->em->find(CmsGroup::class, $this->groupId);
        self::assertFalse($group->users->isInitialized(), 'Pre-Condition');
        $queryCount = $this->getCurrentQueryCount();

        $someUsers  = $group->users->slice(0, 2);
        $otherUsers = $group->users->slice(2, 2);

        self::assertContainsOnly(CmsUser::class, $someUsers);
        self::assertContainsOnly(CmsUser::class, $otherUsers);
        self::assertCount(2, $someUsers);
        self::assertCount(2, $otherUsers);

        // +2 queries executed by slice
        self::assertEquals($queryCount + 2, $this->getCurrentQueryCount(), 'Slicing two parts should only execute two additional queries.');
    }

    /**
     * @group DDC-546
     */
    public function testSliceOneToMany() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        self::assertFalse($user->articles->isInitialized(), 'Pre-Condition: Collection is not initialized.');

        $queryCount = $this->getCurrentQueryCount();

        $someArticle  = $user->articles->slice(0, 1);
        $otherArticle = $user->articles->slice(1, 1);

        self::assertEquals($queryCount + 2, $this->getCurrentQueryCount());
    }

    /**
     * @group DDC-546
     */
    public function testContainsOneToMany() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        self::assertFalse($user->articles->isInitialized(), 'Pre-Condition: Collection is not initialized.');

        // Test One to Many existence retrieved from DB
        $article    = $this->em->find(CmsArticle::class, $this->articleId);
        $queryCount = $this->getCurrentQueryCount();

        self::assertTrue($user->articles->contains($article));
        self::assertFalse($user->articles->isInitialized(), 'Post-Condition: Collection is not initialized.');
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());

        // Test One to Many existence with state new
        $article        = new CmsArticle();
        $article->topic = 'Testnew';
        $article->text  = 'blub';

        $queryCount = $this->getCurrentQueryCount();
        self::assertFalse($user->articles->contains($article));
        self::assertEquals($queryCount, $this->getCurrentQueryCount(), 'Checking for contains of new entity should cause no query to be executed.');

        // Test One to Many existence with state clear
        $this->em->persist($article);
        $this->em->flush();

        $queryCount = $this->getCurrentQueryCount();
        self::assertFalse($user->articles->contains($article));
        self::assertEquals($queryCount+1, $this->getCurrentQueryCount(), 'Checking for contains of persisted entity should cause one query to be executed.');
        self::assertFalse($user->articles->isInitialized(), 'Post-Condition: Collection is not initialized.');

        // Test One to Many existence with state managed
        $article        = new CmsArticle();
        $article->topic = 'How to not fail anymore on tests';
        $article->text  = 'That is simple! Just write more tests!';

        $this->em->persist($article);

        $queryCount = $this->getCurrentQueryCount();

        self::assertFalse($user->articles->contains($article));
        self::assertEquals($queryCount, $this->getCurrentQueryCount(), 'Checking for contains of managed entity (but not persisted) should cause no query to be executed.');
        self::assertFalse($user->articles->isInitialized(), 'Post-Condition: Collection is not initialized.');
    }

    /**
     * @group DDC-2504
     */
    public function testLazyOneToManyJoinedInheritanceIsLazilyInitialized() : void
    {
        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);

        self::assertFalse($otherClass->childClasses->isInitialized(), 'Collection is not initialized.');
    }

    /**
     * @group DDC-2504
     */
    public function testContainsOnOneToManyJoinedInheritanceWillNotInitializeCollectionWhenMatchingItemIsFound() : void
    {
        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);

        // Test One to Many existence retrieved from DB
        $childClass = $this->em->find(DDC2504ChildClass::class, $this->ddc2504ChildClassId);
        $queryCount = $this->getCurrentQueryCount();

        self::assertTrue($otherClass->childClasses->contains($childClass));
        self::assertFalse($otherClass->childClasses->isInitialized(), 'Collection is not initialized.');
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount(), 'Search operation was performed via SQL');
    }

    /**
     * @group DDC-2504
     */
    public function testContainsOnOneToManyJoinedInheritanceWillNotCauseQueriesWhenNonPersistentItemIsMatched() : void
    {
        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);
        $queryCount = $this->getCurrentQueryCount();

        self::assertFalse($otherClass->childClasses->contains(new DDC2504ChildClass()));
        self::assertEquals(
            $queryCount,
            $this->getCurrentQueryCount(),
            'Checking for contains of new entity should cause no query to be executed.'
        );
    }

    /**
     * @group DDC-2504
     */
    public function testContainsOnOneToManyJoinedInheritanceWillNotInitializeCollectionWithClearStateMatchingItem() : void
    {
        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);
        $childClass = new DDC2504ChildClass();

        // Test One to Many existence with state clear
        $this->em->persist($childClass);
        $this->em->flush();

        $queryCount = $this->getCurrentQueryCount();
        self::assertFalse($otherClass->childClasses->contains($childClass));
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount(), 'Checking for contains of persisted entity should cause one query to be executed.');
        self::assertFalse($otherClass->childClasses->isInitialized(), 'Post-Condition: Collection is not initialized.');
    }

    /**
     * @group DDC-2504
     */
    public function testContainsOnOneToManyJoinedInheritanceWillNotInitializeCollectionWithNewStateNotMatchingItem() : void
    {
        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);
        $childClass = new DDC2504ChildClass();

        $this->em->persist($childClass);

        $queryCount = $this->getCurrentQueryCount();

        self::assertFalse($otherClass->childClasses->contains($childClass));
        self::assertEquals($queryCount, $this->getCurrentQueryCount(), 'Checking for contains of managed entity (but not persisted) should cause no query to be executed.');
        self::assertFalse($otherClass->childClasses->isInitialized(), 'Post-Condition: Collection is not initialized.');
    }

    /**
     * @group DDC-2504
     */
    public function testCountingOnOneToManyJoinedInheritanceWillNotInitializeCollection() : void
    {
        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);

        self::assertCount(2, $otherClass->childClasses);

        self::assertFalse($otherClass->childClasses->isInitialized());
    }

    /**
     * @group DDC-546
     */
    public function testContainsManyToMany() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        self::assertFalse($user->groups->isInitialized(), 'Pre-Condition: Collection is not initialized.');

        // Test Many to Many existence retrieved from DB
        $group      = $this->em->find(CmsGroup::class, $this->groupId);
        $queryCount = $this->getCurrentQueryCount();

        self::assertTrue($user->groups->contains($group));
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount(), 'Checking for contains of managed entity should cause one query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');

        // Test Many to Many existence with state new
        $group       = new CmsGroup();
        $group->name = 'A New group!';

        $queryCount = $this->getCurrentQueryCount();

        self::assertFalse($user->groups->contains($group));
        self::assertEquals($queryCount, $this->getCurrentQueryCount(), 'Checking for contains of new entity should cause no query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');

        // Test Many to Many existence with state clear
        $this->em->persist($group);
        $this->em->flush();

        $queryCount = $this->getCurrentQueryCount();

        self::assertFalse($user->groups->contains($group));
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount(), 'Checking for contains of persisted entity should cause one query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');

        // Test Many to Many existence with state managed
        $group       = new CmsGroup();
        $group->name = 'My managed group';

        $this->em->persist($group);

        $queryCount = $this->getCurrentQueryCount();

        self::assertFalse($user->groups->contains($group));
        self::assertEquals($queryCount, $this->getCurrentQueryCount(), 'Checking for contains of managed entity (but not persisted) should cause no query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');
    }

    /**
     * @group DDC-546
     */
    public function testContainsManyToManyInverse() : void
    {
        $group = $this->em->find(CmsGroup::class, $this->groupId);
        self::assertFalse($group->users->isInitialized(), 'Pre-Condition: Collection is not initialized.');

        $user = $this->em->find(CmsUser::class, $this->userId);

        $queryCount = $this->getCurrentQueryCount();
        self::assertTrue($group->users->contains($user));
        self::assertEquals($queryCount+1, $this->getCurrentQueryCount(), 'Checking for contains of managed entity should cause one query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');

        $newUser       = new CmsUser();
        $newUser->name = 'A New group!';

        $queryCount = $this->getCurrentQueryCount();
        self::assertFalse($group->users->contains($newUser));
        self::assertEquals($queryCount, $this->getCurrentQueryCount(), 'Checking for contains of new entity should cause no query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');
    }

    public function testRemoveElementOneToMany() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        self::assertFalse($user->articles->isInitialized(), 'Pre-Condition: Collection is not initialized.');

        // Test One to Many removal with Entity retrieved from DB
        $article    = $this->em->find(CmsArticle::class, $this->articleId);
        $queryCount = $this->getCurrentQueryCount();

        $user->articles->removeElement($article);

        self::assertFalse($user->articles->isInitialized(), 'Post-Condition: Collection is not initialized.');
        self::assertEquals($queryCount, $this->getCurrentQueryCount());

        // Test One to Many removal with Entity state as new
        $article        = new CmsArticle();
        $article->topic = 'Testnew';
        $article->text  = 'blub';

        $queryCount = $this->getCurrentQueryCount();

        $user->articles->removeElement($article);

        self::assertEquals($queryCount, $this->getCurrentQueryCount(), 'Removing a new entity should cause no query to be executed.');

        // Test One to Many removal with Entity state as clean
        $this->em->persist($article);
        $this->em->flush();

        $queryCount = $this->getCurrentQueryCount();

        $user->articles->removeElement($article);

        self::assertEquals($queryCount, $this->getCurrentQueryCount(), "Removing a persisted entity will not cause queries when the owning side doesn't actually change.");
        self::assertFalse($user->articles->isInitialized(), 'Post-Condition: Collection is not initialized.');

        // Test One to Many removal with Entity state as managed
        $article        = new CmsArticle();
        $article->topic = 'How to not fail anymore on tests';
        $article->text  = 'That is simple! Just write more tests!';

        $this->em->persist($article);

        $queryCount = $this->getCurrentQueryCount();

        $user->articles->removeElement($article);

        self::assertEquals($queryCount, $this->getCurrentQueryCount(), 'Removing a managed entity should cause no query to be executed.');
    }

    /**
     * @group DDC-2504
     */
    public function testRemovalOfManagedElementFromOneToManyJoinedInheritanceCollectionDoesNotInitializeIt() : void
    {
        /** @var DDC2504OtherClass $otherClass */
        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);
        /** @var DDC2504ChildClass $childClass */
        $childClass = $this->em->find(DDC2504ChildClass::class, $this->ddc2504ChildClassId);

        $queryCount = $this->getCurrentQueryCount();

        $otherClass->childClasses->removeElement($childClass);
        $childClass->other = null; // updating owning side

        self::assertFalse($otherClass->childClasses->isInitialized(), 'Collection is not initialized.');

        self::assertEquals(
            $queryCount,
            $this->getCurrentQueryCount(),
            'No queries have been executed'
        );

        self::assertTrue(
            $otherClass->childClasses->contains($childClass),
            'Collection item still not updated (needs flushing)'
        );

        $this->em->flush();

        self::assertFalse(
            $otherClass->childClasses->contains($childClass),
            'Referenced item was removed in the transaction'
        );

        self::assertFalse($otherClass->childClasses->isInitialized(), 'Collection is not initialized.');
    }

    /**
     * @group DDC-2504
     */
    public function testRemovalOfNonManagedElementFromOneToManyJoinedInheritanceCollectionDoesNotInitializeIt() : void
    {
        /** @var DDC2504OtherClass $otherClass */
        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);
        $queryCount = $this->getCurrentQueryCount();

        $otherClass->childClasses->removeElement(new DDC2504ChildClass());

        self::assertEquals(
            $queryCount,
            $this->getCurrentQueryCount(),
            'Removing an unmanaged entity should cause no query to be executed.'
        );
    }

    /**
     * @group DDC-2504
     */
    public function testRemovalOfNewElementFromOneToManyJoinedInheritanceCollectionDoesNotInitializeIt() : void
    {
        /** @var DDC2504OtherClass $otherClass */
        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);
        $childClass = new DDC2504ChildClass();

        $this->em->persist($childClass);

        $queryCount = $this->getCurrentQueryCount();

        $otherClass->childClasses->removeElement($childClass);

        self::assertEquals(
            $queryCount,
            $this->getCurrentQueryCount(),
            'Removing a new entity should cause no query to be executed.'
        );
    }

    /**
     * @group DDC-2504
     */
    public function testRemovalOfNewManagedElementFromOneToManyJoinedInheritanceCollectionDoesNotInitializeIt() : void
    {
        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);
        $childClass = new DDC2504ChildClass();

        $this->em->persist($childClass);
        $this->em->flush();

        $queryCount = $this->getCurrentQueryCount();

        $otherClass->childClasses->removeElement($childClass);

        self::assertEquals(
            $queryCount,
            $this->getCurrentQueryCount(),
            'No queries are executed, as the owning side of the association is not actually updated.'
        );
        self::assertFalse($otherClass->childClasses->isInitialized(), 'Collection is not initialized.');
    }

    public function testRemoveElementManyToMany() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        self::assertFalse($user->groups->isInitialized(), 'Pre-Condition: Collection is not initialized.');

        // Test Many to Many removal with Entity retrieved from DB
        $group      = $this->em->find(CmsGroup::class, $this->groupId);
        $queryCount = $this->getCurrentQueryCount();

        self::assertTrue($user->groups->removeElement($group));

        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount(), 'Removing a persisted entity should cause one query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');

        self::assertFalse($user->groups->removeElement($group), 'Removing an already removed element returns false');

        // Test Many to Many removal with Entity state as new
        $group       = new CmsGroup();
        $group->name = 'A New group!';

        $queryCount = $this->getCurrentQueryCount();

        $user->groups->removeElement($group);

        self::assertEquals($queryCount, $this->getCurrentQueryCount(), 'Removing new entity should cause no query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');

        // Test Many to Many removal with Entity state as clean
        $this->em->persist($group);
        $this->em->flush();

        $queryCount = $this->getCurrentQueryCount();

        $user->groups->removeElement($group);

        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount(), 'Removing a persisted entity should cause one query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');

        // Test Many to Many removal with Entity state as managed
        $group       = new CmsGroup();
        $group->name = 'A New group!';

        $this->em->persist($group);

        $queryCount = $this->getCurrentQueryCount();

        $user->groups->removeElement($group);

        self::assertEquals($queryCount, $this->getCurrentQueryCount(), 'Removing a managed entity should cause no query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');
    }

    public function testRemoveElementManyToManyInverse() : void
    {
        $group = $this->em->find(CmsGroup::class, $this->groupId);
        self::assertFalse($group->users->isInitialized(), 'Pre-Condition: Collection is not initialized.');

        $user       = $this->em->find(CmsUser::class, $this->userId);
        $queryCount = $this->getCurrentQueryCount();

        $group->users->removeElement($user);

        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount(), 'Removing a managed entity should cause one query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');

        $newUser       = new CmsUser();
        $newUser->name = 'A New group!';

        $queryCount = $this->getCurrentQueryCount();

        $group->users->removeElement($newUser);

        self::assertEquals($queryCount, $this->getCurrentQueryCount(), 'Removing a new entity should cause no query to be executed.');
        self::assertFalse($user->groups->isInitialized(), 'Post-Condition: Collection is not initialized.');
    }

    /**
     * @group DDC-1399
     */
    public function testCountAfterAddThenFlush() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);

        $newGroup       = new CmsGroup();
        $newGroup->name = 'Test4';

        $user->addGroup($newGroup);
        $this->em->persist($newGroup);

        self::assertFalse($user->groups->isInitialized());
        self::assertCount(4, $user->groups);
        self::assertFalse($user->groups->isInitialized());

        $this->em->flush();

        self::assertCount(4, $user->groups);
    }

    /**
     * @group DDC-1462
     * @group non-cacheable
     */
    public function testSliceOnDirtyCollection() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        /** @var CmsUser $user */

        $newGroup       = new CmsGroup();
        $newGroup->name = 'Test4';

        $user->addGroup($newGroup);
        $this->em->persist($newGroup);

        $qc     = $this->getCurrentQueryCount();
        $groups = $user->groups->slice(0, 10);

        self::assertCount(4, $groups);
        self::assertEquals($qc + 1, $this->getCurrentQueryCount());
    }

    /**
     * @group DDC-1398
     * @group non-cacheable
     */
    public function testGetIndexByIdentifier() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        /** @var CmsUser $user */

        $queryCount  = $this->getCurrentQueryCount();
        $phonenumber = $user->phonenumbers->get($this->phonenumber);

        self::assertFalse($user->phonenumbers->isInitialized());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
        self::assertSame($phonenumber, $this->em->find(CmsPhonenumber::class, $this->phonenumber));

        $article = $user->phonenumbers->get($this->phonenumber);
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount(), 'Getting the same entity should not cause an extra query to be executed');
    }

    /**
     * @group DDC-1398
     */
    public function testGetIndexByOneToMany() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        /** @var CmsUser $user */

        $queryCount = $this->getCurrentQueryCount();

        $article = $user->articles->get($this->topic);

        self::assertFalse($user->articles->isInitialized());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
        self::assertSame($article, $this->em->find(CmsArticle::class, $this->articleId));
    }

    /**
     * @group DDC-1398
     */
    public function testGetIndexByManyToManyInverseSide() : void
    {
        $group = $this->em->find(CmsGroup::class, $this->groupId);
        /** @var CmsGroup $group */

        $queryCount = $this->getCurrentQueryCount();

        $user = $group->users->get($this->username);

        self::assertFalse($group->users->isInitialized());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
        self::assertSame($user, $this->em->find(CmsUser::class, $this->userId));
    }

    /**
     * @group DDC-1398
     */
    public function testGetIndexByManyToManyOwningSide() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        /** @var CmsUser $user */

        $queryCount = $this->getCurrentQueryCount();

        $group = $user->groups->get($this->groupname);

        self::assertFalse($user->groups->isInitialized());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
        self::assertSame($group, $this->em->find(CmsGroup::class, $this->groupId));
    }

    /**
     * @group DDC-1398
     */
    public function testGetNonExistentIndexBy() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        self::assertNull($user->articles->get(-1));
        self::assertNull($user->groups->get(-1));
    }

    public function testContainsKeyIndexByOneToMany() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId);
        /** @var CmsUser $user */

        $queryCount = $this->getCurrentQueryCount();

        $contains = $user->articles->containsKey($this->topic);

        self::assertTrue($contains);
        self::assertFalse($user->articles->isInitialized());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
    }

    public function testContainsKeyIndexByOneToManyJoinedInheritance() : void
    {
        $class = $this->em->getClassMetadata(DDC2504OtherClass::class);
        $class->getProperty('childClasses')->setIndexedBy('id');

        $otherClass = $this->em->find(DDC2504OtherClass::class, $this->ddc2504OtherClassId);

        $queryCount = $this->getCurrentQueryCount();

        $contains = $otherClass->childClasses->containsKey($this->ddc2504ChildClassId);

        self::assertTrue($contains);
        self::assertFalse($otherClass->childClasses->isInitialized());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
    }

    public function testContainsKeyIndexByManyToMany() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId2);

        $group = $this->em->find(CmsGroup::class, $this->groupId);

        $queryCount = $this->getCurrentQueryCount();

        $contains = $user->groups->containsKey($group->name);

        self::assertTrue($contains, 'The item is not into collection');
        self::assertFalse($user->groups->isInitialized(), 'The collection must not be initialized');
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
    }
    public function testContainsKeyIndexByManyToManyNonOwning() : void
    {
        $user  = $this->em->find(CmsUser::class, $this->userId2);
        $group = $this->em->find(CmsGroup::class, $this->groupId);

        $queryCount = $this->getCurrentQueryCount();

        $contains = $group->users->containsKey($user->username);

        self::assertTrue($contains, 'The item is not into collection');
        self::assertFalse($group->users->isInitialized(), 'The collection must not be initialized');
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
    }

    public function testContainsKeyIndexByWithPkManyToMany() : void
    {
        $class = $this->em->getClassMetadata(CmsUser::class);
        $class->getProperty('groups')->setIndexedBy('id');

        $user = $this->em->find(CmsUser::class, $this->userId2);

        $queryCount = $this->getCurrentQueryCount();

        $contains = $user->groups->containsKey($this->groupId);

        self::assertTrue($contains, 'The item is not into collection');
        self::assertFalse($user->groups->isInitialized(), 'The collection must not be initialized');
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
    }
    public function testContainsKeyIndexByWithPkManyToManyNonOwning() : void
    {
        $class = $this->em->getClassMetadata(CmsGroup::class);
        $class->getProperty('users')->setIndexedBy('id');

        $group = $this->em->find(CmsGroup::class, $this->groupId);

        $queryCount = $this->getCurrentQueryCount();

        $contains = $group->users->containsKey($this->userId2);

        self::assertTrue($contains, 'The item is not into collection');
        self::assertFalse($group->users->isInitialized(), 'The collection must not be initialized');
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
    }

    public function testContainsKeyNonExistentIndexByOneToMany() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId2);

        $queryCount = $this->getCurrentQueryCount();

        $contains = $user->articles->containsKey('NonExistentTopic');

        self::assertFalse($contains);
        self::assertFalse($user->articles->isInitialized());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
    }

    public function testContainsKeyNonExistentIndexByManyToMany() : void
    {
        $user = $this->em->find(CmsUser::class, $this->userId2);

        $queryCount = $this->getCurrentQueryCount();

        $contains = $user->groups->containsKey('NonExistentTopic');

        self::assertFalse($contains);
        self::assertFalse($user->groups->isInitialized());
        self::assertEquals($queryCount + 1, $this->getCurrentQueryCount());
    }

    private function loadFixture()
    {
        $user1           = new CmsUser();
        $user1->username = 'beberlei';
        $user1->name     = 'Benjamin';
        $user1->status   = 'active';

        $user2           = new CmsUser();
        $user2->username = 'jwage';
        $user2->name     = 'Jonathan';
        $user2->status   = 'active';

        $user3           = new CmsUser();
        $user3->username = 'romanb';
        $user3->name     = 'Roman';
        $user3->status   = 'active';

        $user4           = new CmsUser();
        $user4->username = 'gblanco';
        $user4->name     = 'Guilherme';
        $user4->status   = 'active';

        $this->em->persist($user1);
        $this->em->persist($user2);
        $this->em->persist($user3);
        $this->em->persist($user4);

        $group1       = new CmsGroup();
        $group1->name = 'Test1';

        $group2       = new CmsGroup();
        $group2->name = 'Test2';

        $group3       = new CmsGroup();
        $group3->name = 'Test3';

        $user1->addGroup($group1);
        $user1->addGroup($group2);
        $user1->addGroup($group3);

        $user2->addGroup($group1);
        $user3->addGroup($group1);
        $user4->addGroup($group1);

        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->persist($group3);

        $article1        = new CmsArticle();
        $article1->topic = 'Test1';
        $article1->text  = 'Test1';
        $article1->setAuthor($user1);

        $article2        = new CmsArticle();
        $article2->topic = 'Test2';
        $article2->text  = 'Test2';
        $article2->setAuthor($user1);

        $this->em->persist($article1);
        $this->em->persist($article2);

        $phonenumber1              = new CmsPhonenumber();
        $phonenumber1->phonenumber = '12345';

        $phonenumber2              = new CmsPhonenumber();
        $phonenumber2->phonenumber = '67890';

        $this->em->persist($phonenumber1);
        $this->em->persist($phonenumber2);

        $user1->addPhonenumber($phonenumber1);

        // DDC-2504
        $otherClass  = new DDC2504OtherClass();
        $childClass1 = new DDC2504ChildClass();
        $childClass2 = new DDC2504ChildClass();

        $childClass1->other = $otherClass;
        $childClass2->other = $otherClass;

        $otherClass->childClasses[] = $childClass1;
        $otherClass->childClasses[] = $childClass2;

        $this->em->persist($childClass1);
        $this->em->persist($childClass2);
        $this->em->persist($otherClass);

        $this->em->flush();
        $this->em->clear();

        $this->articleId           = $article1->id;
        $this->userId              = $user1->getId();
        $this->userId2             = $user2->getId();
        $this->groupId             = $group1->id;
        $this->ddc2504OtherClassId = $otherClass->id;
        $this->ddc2504ChildClassId = $childClass1->id;

        $this->username    = $user1->username;
        $this->groupname   = $group1->name;
        $this->topic       = $article1->topic;
        $this->phonenumber = $phonenumber1->phonenumber;
    }

    /**
     * @group DDC-3343
     */
    public function testRemoveManagedElementFromOneToManyExtraLazyCollectionIsNoOp() : void
    {
        [$userId, $tweetId] = $this->loadTweetFixture();

        /** @var User $user */
        $user = $this->em->find(User::class, $userId);

        $user->tweets->removeElement($this->em->find(Tweet::class, $tweetId));

        $this->em->clear();

        /** @var User $user */
        $user = $this->em->find(User::class, $userId);

        self::assertCount(1, $user->tweets, 'Element was not removed - need to update the owning side first');
    }

    /**
     * @group DDC-3343
     */
    public function testRemoveManagedElementFromOneToManyExtraLazyCollectionWithoutDeletingTheTargetEntityEntryIsNoOp() : void
    {
        [$userId, $tweetId] = $this->loadTweetFixture();

        /** @var User $user */
        $user  = $this->em->find(User::class, $userId);
        $tweet = $this->em->find(Tweet::class, $tweetId);

        $user->tweets->removeElement($tweet);

        $this->em->clear();

        /** @var Tweet $tweet */
        $tweet = $this->em->find(Tweet::class, $tweetId);
        self::assertInstanceOf(
            Tweet::class,
            $tweet,
            'Even though the collection is extra lazy, the tweet should not have been deleted'
        );

        self::assertInstanceOf(
            User::class,
            $tweet->author,
            'Tweet author link has not been removed - need to update the owning side first'
        );
    }

    /**
     * @group DDC-3343
     */
    public function testRemovingManagedLazyProxyFromExtraLazyOneToManyDoesRemoveTheAssociationButNotTheEntity() : void
    {
        [$userId, $tweetId] = $this->loadTweetFixture();

        /** @var User $user */
        $user  = $this->em->find(User::class, $userId);
        $tweet = $this->em->getReference(Tweet::class, $tweetId);

        $user->tweets->removeElement($this->em->getReference(Tweet::class, $tweetId));

        $this->em->clear();

        /** @var Tweet $tweet */
        $tweet = $this->em->find(Tweet::class, $tweet->id);
        self::assertInstanceOf(
            Tweet::class,
            $tweet,
            'Even though the collection is extra lazy, the tweet should not have been deleted'
        );

        self::assertInstanceOf(User::class, $tweet->author);

        /** @var User $user */
        $user = $this->em->find(User::class, $userId);

        self::assertCount(1, $user->tweets, 'Element was not removed - need to update the owning side first');
    }

    /**
     * @group DDC-3343
     */
    public function testRemoveOrphanedManagedElementFromOneToManyExtraLazyCollection() : void
    {
        [$userId, $userListId] = $this->loadUserListFixture();

        /** @var User $user */
        $user = $this->em->find(User::class, $userId);

        $user->userLists->removeElement($this->em->find(UserList::class, $userListId));

        $this->em->clear();

        /** @var User $user */
        $user = $this->em->find(User::class, $userId);

        self::assertCount(0, $user->userLists, 'Element was removed from association due to orphan removal');
        self::assertNull(
            $this->em->find(UserList::class, $userListId),
            'Element was deleted due to orphan removal'
        );
    }

    /**
     * @group DDC-3343
     */
    public function testRemoveOrphanedUnManagedElementFromOneToManyExtraLazyCollection() : void
    {
        [$userId, $userListId] = $this->loadUserListFixture();

        /** @var User $user */
        $user = $this->em->find(User::class, $userId);

        $user->userLists->removeElement(new UserList());

        $this->em->clear();

        /** @var UserList $userList */
        $userList = $this->em->find(UserList::class, $userListId);
        self::assertInstanceOf(
            UserList::class,
            $userList,
            'Even though the collection is extra lazy + orphan removal, the user list should not have been deleted'
        );

        self::assertInstanceOf(
            User::class,
            $userList->owner,
            'User list to owner link has not been removed'
        );
    }

    /**
     * @group DDC-3343
     */
    public function testRemoveOrphanedManagedLazyProxyFromExtraLazyOneToMany() : void
    {
        [$userId, $userListId] = $this->loadUserListFixture();

        /** @var User $user */
        $user = $this->em->find(User::class, $userId);

        $user->userLists->removeElement($this->em->getReference(UserList::class, $userListId));

        $this->em->clear();

        /** @var User $user */
        $user = $this->em->find(User::class, $userId);

        self::assertCount(0, $user->userLists, 'Element was removed from association due to orphan removal');
        self::assertNull(
            $this->em->find(UserList::class, $userListId),
            'Element was deleted due to orphan removal'
        );
    }

    /**
     * @return int[] ordered tuple: user id and tweet id
     */
    private function loadTweetFixture()
    {
        $user  = new User();
        $tweet = new Tweet();

        $user->name     = 'ocramius';
        $tweet->content = 'The cat is on the table';

        $user->addTweet($tweet);

        $this->em->persist($user);
        $this->em->persist($tweet);
        $this->em->flush();
        $this->em->clear();

        return [$user->id, $tweet->id];
    }

    /**
     * @return int[] ordered tuple: user id and user list id
     */
    private function loadUserListFixture()
    {
        $user     = new User();
        $userList = new UserList();

        $user->name         = 'ocramius';
        $userList->listName = 'PHP Developers to follow closely';

        $user->addUserList($userList);

        $this->em->persist($user);
        $this->em->persist($userList);
        $this->em->flush();
        $this->em->clear();

        return [$user->id, $userList->id];
    }
}
