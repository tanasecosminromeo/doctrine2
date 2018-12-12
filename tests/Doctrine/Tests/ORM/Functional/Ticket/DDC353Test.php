<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Annotation as ORM;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;

class DDC353Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();
        try {
            $this->schemaTool->createSchema(
                [
                    $this->em->getClassMetadata(DDC353File::class),
                    $this->em->getClassMetadata(DDC353Picture::class),
                ]
            );
        } catch (Exception $ignored) {
        }
    }

    public function testWorkingCase() : void
    {
        $file = new DDC353File();

        $picture = new DDC353Picture();
        $picture->setFile($file);

        $em = $this->em;
        $em->persist($picture);
        $em->flush();
        $em->clear();

        $fileId = $file->getFileId();
        self::assertGreaterThan(0, $fileId);

        $file = $em->getReference(DDC353File::class, $fileId);
        self::assertEquals(UnitOfWork::STATE_MANAGED, $em->getUnitOfWork()->getEntityState($file), 'Reference Proxy should be marked MANAGED.');

        $picture = $em->find(DDC353Picture::class, $picture->getPictureId());
        self::assertEquals(UnitOfWork::STATE_MANAGED, $em->getUnitOfWork()->getEntityState($picture->getFile()), 'Lazy Proxy should be marked MANAGED.');

        $em->remove($picture);
        $em->flush();
    }

    public function testFailingCase() : void
    {
        $file = new DDC353File();

        $picture = new DDC353Picture();
        $picture->setFile($file);

        $em = $this->em;
        $em->persist($picture);
        $em->flush();
        $em->clear();

        $fileId    = $file->getFileId();
        $pictureId = $picture->getPictureId();

        self::assertGreaterThan(0, $fileId);

        $picture = $em->find(DDC353Picture::class, $pictureId);
        self::assertEquals(UnitOfWork::STATE_MANAGED, $em->getUnitOfWork()->getEntityState($picture->getFile()), 'Lazy Proxy should be marked MANAGED.');

        $em->remove($picture);
        $em->flush();
    }
}

/**
 * @ORM\Entity
 */
class DDC353Picture
{
    /**
     * @ORM\Column(name="picture_id", type="integer")
     * @ORM\Id @ORM\GeneratedValue
     */
    private $pictureId;

    /**
     * @ORM\ManyToOne(targetEntity=DDC353File::class, cascade={"persist", "remove"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="file_id", referencedColumnName="file_id")
     * })
     */
    private $file;

    /**
     * Get pictureId
     */
    public function getPictureId()
    {
        return $this->pictureId;
    }

    /**
     * Set product
     */
    public function setProduct($value)
    {
        $this->product = $value;
    }

    /**
     * Get product
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * Set file
     */
    public function setFile($value)
    {
        $this->file = $value;
    }

    /**
     * Get file
     */
    public function getFile()
    {
        return $this->file;
    }
}

/**
 * @ORM\Entity
 */
class DDC353File
{
    /**
     * @ORM\Column(name="file_id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $fileId;

    /**
     * Get fileId
     */
    public function getFileId()
    {
        return $this->fileId;
    }
}
