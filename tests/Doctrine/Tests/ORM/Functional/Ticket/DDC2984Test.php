<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;
use function is_string;

/**
 * @group DDC-2984
 */
class DDC2984Test extends OrmFunctionalTestCase
{
    public function setUp() : void
    {
        parent::setUp();

        if (! Type::hasType('ddc2984_domain_user_id')) {
            Type::addType(
                'ddc2984_domain_user_id',
                DDC2984UserIdCustomDbalType::class
            );
        }

        try {
            $this->schemaTool->createSchema(
                [
                    $this->em->getClassMetadata(DDC2984User::class),
                ]
            );
        } catch (Exception $e) {
            // no action needed - schema seems to be already in place
        }
    }

    public function testIssue() : void
    {
        $user = new DDC2984User(new DDC2984DomainUserId('unique_id_within_a_vo'));
        $user->applyName('Alex');

        $this->em->persist($user);
        $this->em->flush();

        $repository = $this->em->getRepository(__NAMESPACE__ . '\DDC2984User');

        $sameUser = $repository->find(new DDC2984DomainUserId('unique_id_within_a_vo'));

        //Until know, everything works as expected
        self::assertTrue($user->sameIdentityAs($sameUser));

        $this->em->clear();

        //After clearing the identity map, the UnitOfWork produces the warning described in DDC-2984
        $equalUser = $repository->find(new DDC2984DomainUserId('unique_id_within_a_vo'));

        self::assertNotSame($user, $equalUser);
        self::assertTrue($user->sameIdentityAs($equalUser));
    }
}

/** @ORM\Entity @ORM\Table(name="users") */
class DDC2984User
{
    /**
     * @ORM\Id @ORM\Column(type="ddc2984_domain_user_id")
     * @ORM\GeneratedValue(strategy="NONE")
     *
     * @var DDC2984DomainUserId
     */
    private $userId;

    /** @ORM\Column(type="string", length=50) */
    private $name;

    public function __construct(DDC2984DomainUserId $aUserId)
    {
        $this->userId = $aUserId;
    }

    /**
     * @return DDC2984DomainUserId
     */
    public function userId()
    {
        return $this->userId;
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function applyName($name)
    {
        $this->name = $name;
    }

    /**
     * @return bool
     */
    public function sameIdentityAs(DDC2984User $other)
    {
        return $this->userId()->sameValueAs($other->userId());
    }
}

/**
 * DDC2984DomainUserId ValueObject
 */
class DDC2984DomainUserId
{
    /** @var string */
    private $userIdString;

    /**
     * @param string $aUserIdString
     */
    public function __construct($aUserIdString)
    {
        $this->userIdString = $aUserIdString;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->userIdString;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return bool
     */
    public function sameValueAs(DDC2984DomainUserId $other)
    {
        return $this->toString() === $other->toString();
    }
}

/**
 * Class DDC2984UserIdCustomDbalType
 */
class DDC2984UserIdCustomDbalType extends StringType
{
    public function getName()
    {
        return 'ddc2984_domain_user_id';
    }
    /**
     * {@inheritDoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return ! empty($value)
            ? new DDC2984DomainUserId($value)
            : null;
    }

    /**
     * {@inheritDoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (empty($value)) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (! $value instanceof DDC2984DomainUserId) {
            throw ConversionException::conversionFailed($value, $this->getName());
        }

        return $value->toString();
    }
}
