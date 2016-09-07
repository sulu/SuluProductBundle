<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Doctrine\ORM\EntityManager;
use Sulu\Bundle\ProductBundle\Entity\Status as StatusEntity;
use Sulu\Bundle\ProductBundle\Entity\StatusTranslation as StatusTranslationEntity;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

class StatusManagerTest extends SuluTestCase
{
    /**
     * @var array
     */
    protected static $entities;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var StatusManager
     */
    private $statusManager;

    public function setUp()
    {
        $this->em = $this->getEntityManager();
        $this->purgeDatabase();
        $this->client = $this->createAuthenticatedClient();
        $this->statusManager = $this->client->getContainer()->get('sulu_product.status_manager');
        $this->em->flush();
    }

    public function testFindAll()
    {
        $status1 = new StatusEntity();
        $status1de = new StatusTranslationEntity();
        $status1de->setLocale('de');
        $status1de->setName('Deutscher Status 1');
        $status1de->setStatus($status1);
        $status1en = new StatusTranslationEntity();
        $status1en->setLocale('en');
        $status1en->setName('English status 1');
        $status1en->setStatus($status1);

        $status2 = new StatusEntity();
        $status2de = new StatusTranslationEntity();
        $status2de->setLocale('de');
        $status2de->setName('Deutscher Status 2');
        $status2de->setStatus($status2);
        $status2en = new StatusTranslationEntity();
        $status2en->setLocale('en');
        $status2en->setName('English status 2');
        $status2en->setStatus($status2);

        $this->em->persist($status1);
        $this->em->persist($status1de);
        $this->em->persist($status1en);
        $this->em->persist($status2);
        $this->em->persist($status2de);
        $this->em->persist($status2en);

        $this->em->flush();

        $statuses = $this->statusManager->findAll('de');

        $this->assertCount(2, $statuses);
        $this->assertEquals('Deutscher Status 1', $statuses[0]->getName());
        $this->assertEquals('Deutscher Status 2', $statuses[1]->getName());

        $statuses = $this->statusManager->findAll('en');

        $this->assertCount(2, $statuses);
        $this->assertEquals('English status 1', $statuses[0]->getName());
        $this->assertEquals('English status 2', $statuses[1]->getName());
    }
}
