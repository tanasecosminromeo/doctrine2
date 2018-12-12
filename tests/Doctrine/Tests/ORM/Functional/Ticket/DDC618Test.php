<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\ORM\Query;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;

/**
 * @group DDC-618
 */
class DDC618Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();
        try {
            $this->schemaTool->createSchema(
                [
                    $this->em->getClassMetadata(DDC618Author::class),
                    $this->em->getClassMetadata(DDC618Book::class),
                ]
            );

            // Create author 10/Joe with two books 22/JoeA and 20/JoeB
            $author       = new DDC618Author();
            $author->id   = 10;
            $author->name = 'Joe';
            $this->em->persist($author);

            // Create author 11/Alice with two books 21/AliceA and 23/AliceB
            $author       = new DDC618Author();
            $author->id   = 11;
            $author->name = 'Alice';
            $author->addBook('In Wonderland');
            $author->addBook('Reloaded');
            $author->addBook('Test');

            $this->em->persist($author);

            $this->em->flush();
            $this->em->clear();
        } catch (Exception $e) {
        }
    }

    public function testIndexByHydrateObject() : void
    {
        $dql    = 'SELECT A FROM Doctrine\Tests\ORM\Functional\Ticket\DDC618Author A INDEX BY A.name ORDER BY A.name ASC';
        $result = $this->em->createQuery($dql)->getResult(Query::HYDRATE_OBJECT);

        $joe   = $this->em->find(DDC618Author::class, 10);
        $alice = $this->em->find(DDC618Author::class, 11);

        self::assertArrayHasKey('Joe', $result, "INDEX BY A.name should return an index by the name of 'Joe'.");
        self::assertArrayHasKey('Alice', $result, "INDEX BY A.name should return an index by the name of 'Alice'.");
    }

    public function testIndexByHydrateArray() : void
    {
        $dql    = 'SELECT A FROM Doctrine\Tests\ORM\Functional\Ticket\DDC618Author A INDEX BY A.name ORDER BY A.name ASC';
        $result = $this->em->createQuery($dql)->getResult(Query::HYDRATE_ARRAY);

        $joe   = $this->em->find(DDC618Author::class, 10);
        $alice = $this->em->find(DDC618Author::class, 11);

        self::assertArrayHasKey('Joe', $result, "INDEX BY A.name should return an index by the name of 'Joe'.");
        self::assertArrayHasKey('Alice', $result, "INDEX BY A.name should return an index by the name of 'Alice'.");
    }

    /**
     * @group DDC-1018
     */
    public function testIndexByJoin() : void
    {
        $dql    = 'SELECT A, B FROM Doctrine\Tests\ORM\Functional\Ticket\DDC618Author A ' .
               'INNER JOIN A.books B INDEX BY B.title ORDER BY A.name ASC';
        $result = $this->em->createQuery($dql)->getResult(Query::HYDRATE_OBJECT);

        self::assertCount(3, $result[0]->books); // Alice, Joe doesn't appear because he has no books.
        self::assertEquals('Alice', $result[0]->name);
        self::assertTrue(isset($result[0]->books['In Wonderland']), 'Indexing by title should have books by title.');
        self::assertTrue(isset($result[0]->books['Reloaded']), 'Indexing by title should have books by title.');
        self::assertTrue(isset($result[0]->books['Test']), 'Indexing by title should have books by title.');

        $result = $this->em->createQuery($dql)->getResult(Query::HYDRATE_ARRAY);

        self::assertCount(3, $result[0]['books']); // Alice, Joe doesn't appear because he has no books.
        self::assertEquals('Alice', $result[0]['name']);
        self::assertTrue(isset($result[0]['books']['In Wonderland']), 'Indexing by title should have books by title.');
        self::assertTrue(isset($result[0]['books']['Reloaded']), 'Indexing by title should have books by title.');
        self::assertTrue(isset($result[0]['books']['Test']), 'Indexing by title should have books by title.');
    }

    /**
     * @group DDC-1018
     */
    public function testIndexByToOneJoinSilentlyIgnored() : void
    {
        $dql    = 'SELECT B, A FROM Doctrine\Tests\ORM\Functional\Ticket\DDC618Book B ' .
               'INNER JOIN B.author A INDEX BY A.name ORDER BY A.name ASC';
        $result = $this->em->createQuery($dql)->getResult(Query::HYDRATE_OBJECT);

        self::assertInstanceOf(DDC618Book::class, $result[0]);
        self::assertInstanceOf(DDC618Author::class, $result[0]->author);

        $dql    = 'SELECT B, A FROM Doctrine\Tests\ORM\Functional\Ticket\DDC618Book B ' .
               'INNER JOIN B.author A INDEX BY A.name ORDER BY A.name ASC';
        $result = $this->em->createQuery($dql)->getResult(Query::HYDRATE_ARRAY);

        self::assertEquals('Alice', $result[0]['author']['name']);
    }

    /**
     * @group DDC-1018
     */
    public function testCombineIndexBy() : void
    {
        $dql    = 'SELECT A, B FROM Doctrine\Tests\ORM\Functional\Ticket\DDC618Author A INDEX BY A.id ' .
               'INNER JOIN A.books B INDEX BY B.title ORDER BY A.name ASC';
        $result = $this->em->createQuery($dql)->getResult(Query::HYDRATE_OBJECT);

        self::assertArrayHasKey(11, $result); // Alice

        self::assertCount(3, $result[11]->books); // Alice, Joe doesn't appear because he has no books.
        self::assertEquals('Alice', $result[11]->name);
        self::assertTrue(isset($result[11]->books['In Wonderland']), 'Indexing by title should have books by title.');
        self::assertTrue(isset($result[11]->books['Reloaded']), 'Indexing by title should have books by title.');
        self::assertTrue(isset($result[11]->books['Test']), 'Indexing by title should have books by title.');
    }
}

/**
 * @ORM\Entity
 */
class DDC618Author
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    public $id;

    /** @ORM\Column(type="string") */
    public $name;

    /** @ORM\OneToMany(targetEntity=DDC618Book::class, mappedBy="author", cascade={"persist"}) */
    public $books;

    public function __construct()
    {
        $this->books = new ArrayCollection();
    }

    public function addBook($title)
    {
        $book          = new DDC618Book($title, $this);
        $this->books[] = $book;
    }
}

/**
 * @ORM\Entity
 */
class DDC618Book
{
    /**
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    public $id;

    /** @ORM\Column(type="string") */
    public $title;

    /** @ORM\ManyToOne(targetEntity=DDC618Author::class, inversedBy="books") */
    public $author;

    public function __construct($title, $author)
    {
        $this->title  = $title;
        $this->author = $author;
    }
}
