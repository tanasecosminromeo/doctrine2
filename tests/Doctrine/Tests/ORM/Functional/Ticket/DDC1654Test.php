<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * @group DDC-1654
 */
class DDC1654Test extends OrmFunctionalTestCase
{
    public function setUp() : void
    {
        parent::setUp();
        $this->setUpEntitySchema(
            [
                DDC1654Post::class,
                DDC1654Comment::class,
            ]
        );
    }

    public function tearDown() : void
    {
        $conn = static::$sharedConn;
        $conn->executeUpdate('DELETE FROM ddc1654post_ddc1654comment');
        $conn->executeUpdate('DELETE FROM DDC1654Comment');
        $conn->executeUpdate('DELETE FROM DDC1654Post');
    }

    public function testManyToManyRemoveFromCollectionOrphanRemoval() : void
    {
        $post             = new DDC1654Post();
        $post->comments[] = new DDC1654Comment();
        $post->comments[] = new DDC1654Comment();

        $this->em->persist($post);
        $this->em->flush();

        $post->comments->remove(0);
        $post->comments->remove(1);

        $this->em->flush();
        $this->em->clear();

        $comments = $this->em->getRepository(DDC1654Comment::class)->findAll();
        self::assertCount(0, $comments);
    }

    public function testManyToManyRemoveElementFromCollectionOrphanRemoval() : void
    {
        $post             = new DDC1654Post();
        $post->comments[] = new DDC1654Comment();
        $post->comments[] = new DDC1654Comment();

        $this->em->persist($post);
        $this->em->flush();

        $post->comments->removeElement($post->comments[0]);
        $post->comments->removeElement($post->comments[1]);

        $this->em->flush();
        $this->em->clear();

        $comments = $this->em->getRepository(DDC1654Comment::class)->findAll();
        self::assertCount(0, $comments);
    }

    /**
     * @group DDC-3382
     */
    public function testManyToManyRemoveElementFromReAddToCollectionOrphanRemoval() : void
    {
        $post             = new DDC1654Post();
        $post->comments[] = new DDC1654Comment();
        $post->comments[] = new DDC1654Comment();

        $this->em->persist($post);
        $this->em->flush();

        $comment = $post->comments[0];
        $post->comments->removeElement($comment);
        $post->comments->add($comment);

        $this->em->flush();
        $this->em->clear();

        $comments = $this->em->getRepository(DDC1654Comment::class)->findAll();
        self::assertCount(2, $comments);
    }

    public function testManyToManyClearCollectionOrphanRemoval() : void
    {
        $post             = new DDC1654Post();
        $post->comments[] = new DDC1654Comment();
        $post->comments[] = new DDC1654Comment();

        $this->em->persist($post);
        $this->em->flush();

        $post->comments->clear();

        $this->em->flush();
        $this->em->clear();

        $comments = $this->em->getRepository(DDC1654Comment::class)->findAll();
        self::assertCount(0, $comments);
    }

    /**
     * @group DDC-3382
     */
    public function testManyToManyClearCollectionReAddOrphanRemoval() : void
    {
        $post             = new DDC1654Post();
        $post->comments[] = new DDC1654Comment();
        $post->comments[] = new DDC1654Comment();

        $this->em->persist($post);
        $this->em->flush();

        $comment = $post->comments[0];
        $post->comments->clear();
        $post->comments->add($comment);

        $this->em->flush();
        $this->em->clear();

        $comments = $this->em->getRepository(DDC1654Comment::class)->findAll();
        self::assertCount(1, $comments);
    }
}

/**
 * @ORM\Entity
 */
class DDC1654Post
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;

    /**
     * @ORM\ManyToMany(targetEntity=DDC1654Comment::class, orphanRemoval=true,
     * cascade={"persist"})
     */
    public $comments = [];
}

/**
 * @ORM\Entity
 */
class DDC1654Comment
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;
}
