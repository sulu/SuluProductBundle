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
use Sulu\Bundle\ProductBundle\Entity\TaxClass as TaxClassEntity;
use Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation as TaxClassTranslationEntity;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

class TaxClassManagerTest extends SuluTestCase
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
     * @var TaxClassManager
     */
    private $taxClassManager;

    public function setUp()
    {
        $this->em = $this->getEntityManager();
        $this->purgeDatabase();
        $this->client = $this->createAuthenticatedClient();
        $this->taxClassManager = $this->client->getContainer()->get('sulu_product.tax_class_manager');
        $this->em->flush();
    }

    public function testFindAll()
    {
        $taxClass1 = new TaxClassEntity();
        $taxClass1de = new TaxClassTranslationEntity();
        $taxClass1de->setLocale('de');
        $taxClass1de->setName('Deutscher Steuerklasse 1');
        $taxClass1de->setTaxClass($taxClass1);
        $taxClass1en = new TaxClassTranslationEntity();
        $taxClass1en->setLocale('en');
        $taxClass1en->setName('English tax class 1');
        $taxClass1en->setTaxClass($taxClass1);

        $taxClass2 = new TaxClassEntity();
        $taxClass2de = new TaxClassTranslationEntity();
        $taxClass2de->setLocale('de');
        $taxClass2de->setName('Deutscher Steuerklasse 2');
        $taxClass2de->setTaxClass($taxClass2);
        $taxClass2en = new TaxClassTranslationEntity();
        $taxClass2en->setLocale('en');
        $taxClass2en->setName('English tax class 2');
        $taxClass2en->setTaxClass($taxClass2);

        $this->em->persist($taxClass1);
        $this->em->persist($taxClass1de);
        $this->em->persist($taxClass1en);
        $this->em->persist($taxClass2);
        $this->em->persist($taxClass2de);
        $this->em->persist($taxClass2en);

        $this->em->flush();

        $statuses = $this->taxClassManager->findAll('de');

        $this->assertCount(2, $statuses);
        $this->assertEquals('Deutscher Steuerklasse 1', $statuses[0]->getName());
        $this->assertEquals('Deutscher Steuerklasse 2', $statuses[1]->getName());

        $statuses = $this->taxClassManager->findAll('en');

        $this->assertCount(2, $statuses);
        $this->assertEquals('English tax class 1', $statuses[0]->getName());
        $this->assertEquals('English tax class 2', $statuses[1]->getName());
    }
}
