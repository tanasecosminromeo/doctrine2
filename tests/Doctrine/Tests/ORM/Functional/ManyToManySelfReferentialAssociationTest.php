<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\ORM\Mapping\FetchMode;
use Doctrine\Tests\Models\ECommerce\ECommerceProduct;

/**
 * Tests a self referential many-to-many association mapping (from a model to the same model, without inheritance).
 * For simplicity the relation duplicates entries in the association table
 * to remain symmetrical.
 */
class ManyToManySelfReferentialAssociationTest extends AbstractManyToManyAssociationTestCase
{
    protected $firstField  = 'product_id';
    protected $secondField = 'related_id';
    protected $table       = 'ecommerce_products_related';
    private $firstProduct;
    private $secondProduct;
    private $firstRelated;
    private $secondRelated;

    protected function setUp() : void
    {
        $this->useModelSet('ecommerce');

        parent::setUp();

        $this->firstProduct  = new ECommerceProduct();
        $this->secondProduct = new ECommerceProduct();
        $this->firstRelated  = new ECommerceProduct();

        $this->firstRelated->setName('Business');

        $this->secondRelated = new ECommerceProduct();

        $this->secondRelated->setName('Home');
    }

    public function testSavesAManyToManyAssociationWithCascadeSaveSet() : void
    {
        $this->firstProduct->addRelated($this->firstRelated);
        $this->firstProduct->addRelated($this->secondRelated);

        $this->em->persist($this->firstProduct);
        $this->em->flush();

        self::assertForeignKeysContain($this->firstProduct->getId(), $this->firstRelated->getId());
        self::assertForeignKeysContain($this->firstProduct->getId(), $this->secondRelated->getId());
    }

    public function testRemovesAManyToManyAssociation() : void
    {
        $this->firstProduct->addRelated($this->firstRelated);
        $this->firstProduct->addRelated($this->secondRelated);

        $this->em->persist($this->firstProduct);

        $this->firstProduct->removeRelated($this->firstRelated);

        $this->em->flush();

        self::assertForeignKeysNotContain($this->firstProduct->getId(), $this->firstRelated->getId());
        self::assertForeignKeysContain($this->firstProduct->getId(), $this->secondRelated->getId());
    }

    public function testEagerLoadsOwningSide() : void
    {
        $this->createLoadingFixture();

        $products = $this->findProducts();

        self::assertLoadingOfOwningSide($products);
    }

    public function testLazyLoadsOwningSide() : void
    {
        $this->createLoadingFixture();

        $metadata = $this->em->getClassMetadata(ECommerceProduct::class);
        $metadata->getProperty('related')->setFetchMode(FetchMode::LAZY);

        $query    = $this->em->createQuery('SELECT p FROM Doctrine\Tests\Models\ECommerce\ECommerceProduct p');
        $products = $query->getResult();

        self::assertLoadingOfOwningSide($products);
    }

    public function assertLoadingOfOwningSide($products)
    {
        [$firstProduct, $secondProduct] = $products;
        self::assertCount(2, $firstProduct->getRelated());
        self::assertCount(2, $secondProduct->getRelated());

        $categories      = $firstProduct->getRelated();
        $firstRelatedBy  = $categories[0]->getRelated();
        $secondRelatedBy = $categories[1]->getRelated();

        self::assertCount(2, $firstRelatedBy);
        self::assertCount(2, $secondRelatedBy);

        self::assertInstanceOf(ECommerceProduct::class, $firstRelatedBy[0]);
        self::assertInstanceOf(ECommerceProduct::class, $firstRelatedBy[1]);
        self::assertInstanceOf(ECommerceProduct::class, $secondRelatedBy[0]);
        self::assertInstanceOf(ECommerceProduct::class, $secondRelatedBy[1]);

        self::assertCollectionEquals($firstRelatedBy, $secondRelatedBy);
    }

    protected function createLoadingFixture()
    {
        $this->firstProduct->addRelated($this->firstRelated);
        $this->firstProduct->addRelated($this->secondRelated);
        $this->secondProduct->addRelated($this->firstRelated);
        $this->secondProduct->addRelated($this->secondRelated);
        $this->em->persist($this->firstProduct);
        $this->em->persist($this->secondProduct);

        $this->em->flush();
        $this->em->clear();
    }

    protected function findProducts()
    {
        $query = $this->em->createQuery('SELECT p, r FROM Doctrine\Tests\Models\ECommerce\ECommerceProduct p LEFT JOIN p.related r ORDER BY p.id, r.id');
        return $query->getResult();
    }
}
