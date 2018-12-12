<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * @group DDC-1151
 */
class DDC1151Test extends OrmFunctionalTestCase
{
    public function testQuoteForeignKey() : void
    {
        if ($this->em->getConnection()->getDatabasePlatform()->getName() !== 'postgresql') {
            $this->markTestSkipped('This test is useful for all databases, but designed only for postgresql.');
        }

        $sql = $this->schemaTool->getCreateSchemaSql(
            [
                $this->em->getClassMetadata(DDC1151User::class),
                $this->em->getClassMetadata(DDC1151Group::class),
            ]
        );

        self::assertEquals('CREATE TABLE "User" (id INT NOT NULL, PRIMARY KEY(id))', $sql[0]);
        self::assertEquals('CREATE TABLE ddc1151user_ddc1151group (ddc1151user_id INT NOT NULL, ddc1151group_id INT NOT NULL, PRIMARY KEY(ddc1151user_id, ddc1151group_id))', $sql[1]);
        self::assertEquals('CREATE INDEX IDX_88A3259AC5AD08A ON ddc1151user_ddc1151group (ddc1151user_id)', $sql[2]);
        self::assertEquals('CREATE INDEX IDX_88A32597357E0B1 ON ddc1151user_ddc1151group (ddc1151group_id)', $sql[3]);
        self::assertEquals('CREATE TABLE "Group" (id INT NOT NULL, PRIMARY KEY(id))', $sql[4]);
        self::assertEquals('CREATE SEQUENCE "User_id_seq" INCREMENT BY 1 MINVALUE 1 START 1', $sql[5]);
        self::assertEquals('CREATE SEQUENCE "Group_id_seq" INCREMENT BY 1 MINVALUE 1 START 1', $sql[6]);
        self::assertEquals('ALTER TABLE ddc1151user_ddc1151group ADD CONSTRAINT FK_88A3259AC5AD08A FOREIGN KEY (ddc1151user_id) REFERENCES "User" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE', $sql[7]);
        self::assertEquals('ALTER TABLE ddc1151user_ddc1151group ADD CONSTRAINT FK_88A32597357E0B1 FOREIGN KEY (ddc1151group_id) REFERENCES "Group" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE', $sql[8]);
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="User")
 */
class DDC1151User
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;

    /** @ORM\ManyToMany(targetEntity=DDC1151Group::class) */
    public $groups;
}

/**
 * @ORM\Entity
 * @ORM\Table(name="Group")
 */
class DDC1151Group
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue */
    public $id;
}
