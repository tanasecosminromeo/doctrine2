<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

class DDC809Test extends OrmFunctionalTestCase
{
    public function setUp() : void
    {
        parent::setUp();
        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC809Variant::class),
                $this->em->getClassMetadata(DDC809SpecificationValue::class),
            ]
        );

        $conn = $this->em->getConnection();
        $conn->insert('specification_value_test', ['specification_value_id' => 94589]);
        $conn->insert('specification_value_test', ['specification_value_id' => 94593]);
        $conn->insert('specification_value_test', ['specification_value_id' => 94606]);
        $conn->insert('specification_value_test', ['specification_value_id' => 94607]);
        $conn->insert('specification_value_test', ['specification_value_id' => 94609]);
        $conn->insert('specification_value_test', ['specification_value_id' => 94711]);

        $conn->insert('variant_test', ['variant_id' => 545208]);
        $conn->insert('variant_test', ['variant_id' => 545209]);

        $conn->insert('var_spec_value_test', ['variant_id' => 545208, 'specification_value_id' => 94606]);
        $conn->insert('var_spec_value_test', ['variant_id' => 545208, 'specification_value_id' => 94607]);
        $conn->insert('var_spec_value_test', ['variant_id' => 545208, 'specification_value_id' => 94609]);
        $conn->insert('var_spec_value_test', ['variant_id' => 545208, 'specification_value_id' => 94711]);

        $conn->insert('var_spec_value_test', ['variant_id' => 545209, 'specification_value_id' => 94589]);
        $conn->insert('var_spec_value_test', ['variant_id' => 545209, 'specification_value_id' => 94593]);
        $conn->insert('var_spec_value_test', ['variant_id' => 545209, 'specification_value_id' => 94606]);
        $conn->insert('var_spec_value_test', ['variant_id' => 545209, 'specification_value_id' => 94607]);
    }

    /**
     * @group DDC-809
     */
    public function testIssue() : void
    {
        $result = $this->em->createQueryBuilder()
                        ->select('Variant, SpecificationValue')
                        ->from(DDC809Variant::class, 'Variant')
                        ->leftJoin('Variant.SpecificationValues', 'SpecificationValue')
                        ->getQuery()
                        ->getResult();

        self::assertCount(4, $result[0]->getSpecificationValues(), 'Works in test-setup.');
        self::assertCount(4, $result[1]->getSpecificationValues(), 'Only returns 2 in the case of the hydration bug.');
    }
}

/**
 * @ORM\Table(name="variant_test")
 * @ORM\Entity
 */
class DDC809Variant
{
    /**
     * @ORM\Column(name="variant_id", type="integer")
     * @ORM\Id
     */
    protected $variantId;

    /**
     * @ORM\ManyToMany(targetEntity=DDC809SpecificationValue::class, inversedBy="Variants")
     * @ORM\JoinTable(name="var_spec_value_test",
     *   joinColumns={
     *     @ORM\JoinColumn(name="variant_id", referencedColumnName="variant_id")
     *   },
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="specification_value_id", referencedColumnName="specification_value_id")
     *   }
     * )
     */
    protected $SpecificationValues;

    public function getSpecificationValues()
    {
        return $this->SpecificationValues;
    }
}

/**
 * @ORM\Table(name="specification_value_test")
 * @ORM\Entity
 */
class DDC809SpecificationValue
{
    /**
     * @ORM\Column(name="specification_value_id", type="integer")
     * @ORM\Id
     */
    protected $specificationValueId;

    /**
     * @ORM\ManyToMany(targetEntity=DDC809Variant::class, mappedBy="SpecificationValues")
     *
     * @var DDC809Variant
     */
    protected $Variants;
}
