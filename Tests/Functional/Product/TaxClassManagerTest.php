<?php
/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Doctrine\ORM\Tools\SchemaTool;
use Sulu\Bundle\TestBundle\Testing\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Sulu\Bundle\ProductBundle\Entity\TaxClass as TaxClassEntity;
use Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation as TaxClassTranslationEntity;

class TaxClassManagerTest extends DatabaseTestCase
{
    /**
     * @var array
     */
    protected static $entities;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var TaxClassManager
     */
    private $taxClassManager;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$entities = array(
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\TaxClass'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation'),
        );
    }

    public function setUp()
    {
        $this->setUpSchema();

        $this->client = $this->createClient();

        $this->taxClassManager = $this->client->getContainer()->get('sulu_product.tax_class_manager');
    }

    private function setUpSchema()
    {
        self::$tool = new SchemaTool(self::$em);

        self::$tool->dropSchema(self::$entities);
        self::$tool->createSchema(self::$entities);
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

        self::$em->persist($taxClass1);
        self::$em->persist($taxClass1de);
        self::$em->persist($taxClass1en);
        self::$em->persist($taxClass2);
        self::$em->persist($taxClass2de);
        self::$em->persist($taxClass2en);

        self::$em->flush();

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
