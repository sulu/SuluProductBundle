<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Tests\Functional\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use Sulu\Bundle\ProductBundle\Tests\Resources\ProductTestData;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

class ProductTestDataTest extends SuluTestCase
{
    /**
     * @var ObjectManager
     */
    private $em;

    /**
     * @var Client
     */
    private $client;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->em = $this->getEntityManager();
        $this->purgeDatabase();
        $this->client = $this->createAuthenticatedClient();
        $this->em->flush();
    }

    public function testLoadProductTestData()
    {
        $testData = new ProductTestData($this->getContainer());
        $this->em->flush();

        $this->assertNotNull($testData);
    }
}
