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

use Doctrine\ORM\EntityManager;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\Currencies\LoadCurrencies;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

class CurrencyControllerTest extends SuluTestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var Client
     */
    protected $client;

    public function setUp()
    {
        $this->em = $this->getEntityManager();
        $this->purgeDatabase();
        $this->setUpTestData();
        $this->client = $this->createAuthenticatedClient();
        $this->em->flush();
    }

    private function setUpTestData()
    {
        $loadCurrencies = new LoadCurrencies();
        $loadCurrencies->load($this->em);
    }

    public function testcgetAction()
    {
        $this->client->request('GET', '/api/currencies');
        $response = json_decode($this->client->getResponse()->getContent());

        $currencies = $response->_embedded->currencies;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertCount(2, $currencies);
    }
}
