<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Tests\Models\CMS\CmsGroup;
use Doctrine\Tests\Models\CMS\CmsUser;
use Doctrine\Tests\OrmFunctionalTestCase;
use Exception;
use function get_class;

class DDC767Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        $this->useModelSet('cms');
        parent::setUp();
    }

    /**
     * @group DDC-767
     */
    public function testCollectionChangesInsideTransaction() : void
    {
        $user           = new CmsUser();
        $user->name     = 'beberlei';
        $user->status   = 'active';
        $user->username = 'beberlei';

        $group1       = new CmsGroup();
        $group1->name = 'foo';

        $group2       = new CmsGroup();
        $group2->name = 'bar';

        $group3       = new CmsGroup();
        $group3->name = 'baz';

        $user->addGroup($group1);
        $user->addGroup($group2);

        $this->em->persist($user);
        $this->em->persist($group1);
        $this->em->persist($group2);
        $this->em->persist($group3);

        $this->em->flush();
        $this->em->clear();

        /** @var CmsUser $pUser */
        $pUser = $this->em->find(get_class($user), $user->id);

        self::assertNotNull($pUser, 'User not retrieved from database.');

        $groups = [$group2->id, $group3->id];

        try {
            $this->em->beginTransaction();

            $pUser->groups->clear();

            $this->em->flush();

            // Add new
            foreach ($groups as $groupId) {
                $pUser->addGroup($this->em->find(get_class($group1), $groupId));
            }

            $this->em->flush();
            $this->em->commit();
        } catch (Exception $e) {
            $this->em->rollback();
        }
    }
}
