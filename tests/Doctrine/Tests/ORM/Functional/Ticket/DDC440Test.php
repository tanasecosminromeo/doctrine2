<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;

class DDC440Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();
        try {
            $this->schemaTool->createSchema(
                [
                    $this->em->getClassMetadata(DDC440Phone::class),
                    $this->em->getClassMetadata(DDC440Client::class),
                ]
            );
        } catch (Exception $e) {
            // Swallow all exceptions. We do not test the schema tool here.
        }
    }

    /**
     * @group DDC-440
     */
    public function testOriginalEntityDataEmptyWhenProxyLoadedFromTwoAssociations() : void
    {
        /* The key of the problem is that the first phone is fetched via two association, main_phone and phones.
         *
         * You will notice that the original_entity_datas are not loaded for the first phone. (They are for the second)
         *
         * In the Client entity definition, if you define the main_phone relation after the phones relation, both assertions pass.
         * (for the sake or this test, I defined the main_phone relation before the phones relation)
         *
         */

        //Initialize some data
        $client = new DDC440Client();
        $client->setName('Client1');

        $phone = new DDC440Phone();
        $phone->setId(1);
        $phone->setNumber('418 111-1111');
        $phone->setClient($client);

        $phone2 = new DDC440Phone();
        $phone->setId(2);
        $phone2->setNumber('418 222-2222');
        $phone2->setClient($client);

        $client->setMainPhone($phone);

        $this->em->persist($client);
        $this->em->flush();
        $id = $client->getId();
        $this->em->clear();

        $uw           = $this->em->getUnitOfWork();
        $client       = $this->em->find(DDC440Client::class, $id);
        $clientPhones = $client->getPhones();

        $p1 = $clientPhones[1];
        $p2 = $clientPhones[2];

        // Test the first phone.  The assertion actually failed because original entity data is not set properly.
        // This was because it is also set as MainPhone and that one is created as a proxy, not the
        // original object when the find on Client is called. However loading proxies did not work correctly.
        self::assertInstanceOf(DDC440Phone::class, $p1);
        $originalData = $uw->getOriginalEntityData($p1);
        self::assertEquals($phone->getNumber(), $originalData['number']);

        //If you comment out previous test, this one should pass
        self::assertInstanceOf(DDC440Phone::class, $p2);
        $originalData = $uw->getOriginalEntityData($p2);
        self::assertEquals($phone2->getNumber(), $originalData['number']);
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="phone")
 */
class DDC440Phone
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
     * @ORM\ManyToOne(targetEntity=DDC440Client::class,inversedBy="phones")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="client_id", referencedColumnName="id")
     * })
     */
    protected $client;
    /** @ORM\Column(name="phonenumber", type="string") */
    protected $number;

    public function setNumber($value)
    {
        $this->number = $value;
    }

    public function getNumber()
    {
        return $this->number;
    }

    public function setClient(DDC440Client $value, $update_inverse = true)
    {
        $this->client = $value;
        if ($update_inverse) {
            $value->addPhone($this);
        }
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="client")
 */
class DDC440Client
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
     * @ORM\OneToOne(targetEntity=DDC440Phone::class, fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="main_phone_id", referencedColumnName="id",onDelete="SET NULL")
     * })
     */
    protected $main_phone;
    /**
     * @ORM\OneToMany(targetEntity=DDC440Phone::class, mappedBy="client", cascade={"persist", "remove"}, fetch="EAGER", indexBy="id")
     * @ORM\OrderBy({"number"="ASC"})
     */
    protected $phones;
    /** @ORM\Column(name="name", type="string") */
    protected $name;

    public function __construct()
    {
    }

    public function setName($value)
    {
        $this->name = $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function addPhone(DDC440Phone $value)
    {
        $this->phones[] = $value;
        $value->setClient($this, false);
    }

    public function getPhones()
    {
        return $this->phones;
    }

    public function setMainPhone(DDC440Phone $value)
    {
        $this->main_phone = $value;
    }

    public function getMainPhone()
    {
        return $this->main_phone;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
    }
}
