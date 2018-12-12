<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use DateTime;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

class DDC425Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();
        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC425Entity::class),
            ]
        );
    }

    /**
     * @group DDC-425
     */
    public function testIssue() : void
    {
        //$this->em->getConnection()->getConfiguration()->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger);

        $num = $this->em->createQuery('DELETE ' . __NAMESPACE__ . '\DDC425Entity e WHERE e.someDatetimeField > ?1')
                ->setParameter(1, new DateTime(), Type::DATETIME)
                ->getResult();
        self::assertEquals(0, $num);
    }
}

/** @ORM\Entity */
class DDC425Entity
{
    /**
     * @ORM\Id @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    public $id;

    /** @ORM\Column(type="datetime") */
    public $someDatetimeField;
}
