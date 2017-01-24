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

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\CategoryBundle\Entity\CategoryTranslation;
use Sulu\Bundle\ProductBundle\Entity\Addon;
use Sulu\Bundle\ProductBundle\Entity\AddonPrice;
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\AttributeSet;
use Sulu\Bundle\ProductBundle\Entity\AttributeSetTranslation;
use Sulu\Bundle\ProductBundle\Entity\AttributeTranslation;
use Sulu\Bundle\ProductBundle\Entity\AttributeType;
use Sulu\Bundle\ProductBundle\Entity\AttributeValue;
use Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation;
use Sulu\Bundle\ProductBundle\Entity\Currency;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatus;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatusTranslation;
use Sulu\Bundle\ProductBundle\Entity\Product;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Entity\SpecialPrice;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\StatusTranslation;
use Sulu\Bundle\ProductBundle\Entity\TaxClass;
use Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Tests\Resources\ContactTestData;
use Sulu\Bundle\TagBundle\Entity\Tag;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

class ProductAddonControllerTest extends SuluTestCase
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
     * @var ProductPrice
     */
    protected $productPrice1;

    /**
     * @var ProductPrice
     */
    protected $productPrice2;

    /**
     * @var DeliveryStatus
     */
    protected $deliveryStatusAvailable;

    /**
     * @var Product
     */
    private $product1;

    /**
     * @var Type
     */
    private $type1;

    /**
     * @var Status
     */
    private $productStatusActive;

    /**
     * @var Status
     */
    private $productStatusInactive;

    /**
     * @var Status
     */
    private $productStatusChanged;

    /**
     * @var StatusTranslation
     */
    private $productStatusTranslationActive;

    /**
     * @var StatusTranslation
     */
    private $productStatusTranslationInactive;

    /**
     * @var StatusTranslation
     */
    private $productStatusTranslationChanged;

    /**
     * @var AttributeType
     */
    private $attributeType1;

    /**
     * @var AttributeType
     */
    private $attributeType2;

    /**
     * @var AttributeSet
     */
    private $attributeSet1;

    /**
     * @var ProductAttribute
     */
    private $productAttribute1;

    /**
     * @var Attribute
     */
    private $attribute1;

    /**
     * @var AttributeSetTranslation
     */
    private $attributeSetTranslation1;

    /**
     * @var AttributeTranslation
     */
    private $attributeTranslation1;

    /**
     * @var AttributeValue
     */
    private $attributeValue1;

    /**
     * @var AttributeValueTranslation
     */
    private $attributeValueTranslation1;

    /**
     * @var Product
     */
    private $product2;

    /**
     * @var Type
     */
    private $type2;

    /**
     * @var AttributeSet
     */
    private $attributeSet2;

    /**
     * @var ProductAttribute
     */
    private $productAttribute2;

    /**
     * @var Attribute
     */
    private $attribute2;

    /**
     * @var AttributeSetTranslation
     */
    private $attributeSetTranslation2;

    /**
     * @var AttributeTranslation
     */
    private $attributeTranslation2;

    /**
     * @var AttributeValue
     */
    private $attributeValue2;

    /**
     * @var AttributeValueTranslation
     */
    private $attributeValueTranslation2;

    /**
     * @var TaxClass
     */
    private $taxClass1;

    /**
     * @var Currency
     */
    private $currency1;

    /**
     * @var Currency
     */
    private $currency2;

    /**
     * @var Currency
     */
    private $currency3;

    /**
     * @var Category
     */
    private $category1;

    /**
     * @var Category
     */
    private $category2;

    /**
     * @var SpecialPrice
     */
    private $specialPrice1;

    /**
     * @var ContactTestData
     */
    private $contactTestData;

    /**
     * @var Tag
     */
    private $tag1;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Tag
     */
    private $tag2;

    /**
     * @var Addon
     */
    private $addon;

    /**
     * @var AddonPrice
     */
    private $addonPrice;

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
        $this->currency1 = new Currency();
        $this->currency1->setName('EUR');
        $this->currency1->setNumber('1');
        $this->currency1->setCode('EUR');

        $this->currency2 = new Currency();
        $this->currency2->setName('USD');
        $this->currency2->setNumber('2');
        $this->currency2->setCode('USD');

        $this->currency3 = new Currency();
        $this->currency3->setName('GBP');
        $this->currency3->setNumber('3');
        $this->currency3->setCode('GBP');

        // Product 1
        // Product Type
        $this->type1 = new Type();
        $this->type1->setTranslationKey('Type1');

        // Product status active
        $metadata = $this->em->getClassMetadata(get_class(new Status()));
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $this->productStatusActive = new Status();
        $this->productStatusActive->setId(Status::ACTIVE);
        $this->productStatusTranslationActive = new StatusTranslation();
        $this->productStatusTranslationActive->setLocale('en');
        $this->productStatusTranslationActive->setName('EnglishProductStatus-Active');
        $this->productStatusTranslationActive->setStatus($this->productStatusActive);

        // Product status inactive
        $metadata = $this->em->getClassMetadata(get_class(new Status()));
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $this->productStatusInactive = new Status();
        $this->productStatusInactive->setId(Status::INACTIVE);
        $this->productStatusTranslationInactive = new StatusTranslation();
        $this->productStatusTranslationInactive->setLocale('en');
        $this->productStatusTranslationInactive->setName('EnglishProductStatus-Inactive');
        $this->productStatusTranslationInactive->setStatus($this->productStatusInactive);

        // Product status changed
        $metadata = $this->em->getClassMetadata(get_class(new Status()));
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $this->productStatusChanged = new Status();
        $this->productStatusChanged->setId(Status::CHANGED);
        $this->productStatusTranslationChanged = new StatusTranslation();
        $this->productStatusTranslationChanged->setLocale('en');
        $this->productStatusTranslationChanged->setName('EnglishProductStatus-Changed');
        $this->productStatusTranslationChanged->setStatus($this->productStatusChanged);

        // AttributeSet
        $this->attributeSet1 = new AttributeSet();
        $this->attributeSetTranslation1 = new AttributeSetTranslation();
        $this->attributeSetTranslation1->setLocale('en');
        $this->attributeSetTranslation1->setName('EnglishTemplate-1');
        $this->attributeSetTranslation1->setAttributeSet($this->attributeSet1);

        // Attributes
        $this->attributeType1 = new AttributeType();
        $this->attributeType1->setName('EnglishAttributeType-1');

        $metadata = $this->em->getClassMetadata(get_class(new Attribute()));
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);

        $this->attribute1 = new Attribute();
        $this->attribute1->setId(Attribute::ATTRIBUTE_TYPE_TEXT);
        $this->attribute1->setCreated(new DateTime());
        $this->attribute1->setChanged(new DateTime());
        $this->attribute1->setType($this->attributeType1);
        $this->attribute1->setKey('key-1');

        // Attribute Translations
        $this->attributeTranslation1 = new AttributeTranslation();
        $this->attributeTranslation1->setAttribute($this->attribute1);
        $this->attributeTranslation1->setLocale('en');
        $this->attributeTranslation1->setName('EnglishAttribute-1');

        // Attribute Value
        $this->attributeValue1 = new AttributeValue();
        $this->attributeValue1->setAttribute($this->attribute1);

        // Attribute Value Translation
        $this->attributeValueTranslation1 = new AttributeValueTranslation();
        $this->attributeValueTranslation1->setLocale('en');
        $this->attributeValueTranslation1->setName('EnglishAttributeValue-1');
        $this->attributeValueTranslation1->setAttributeValue($this->attributeValue1);

        // Product
        $this->product1 = new Product();
        $this->product1->setNumber('ProductNumber-1');
        $this->product1->setManufacturer('EnglishManufacturer-1');
        $this->product1->setType($this->type1);
        $this->product1->setStatus($this->productStatusInactive);
        $this->product1->setAttributeSet($this->attributeSet1);

        $this->productPrice1 = new ProductPrice();
        $this->productPrice1->setCurrency($this->currency1);
        $this->productPrice1->setPrice(14.99);
        $this->productPrice1->setProduct($this->product1);
        $this->product1->addPrice($this->productPrice1);

        $this->productPrice2 = new ProductPrice();
        $this->productPrice2->setCurrency($this->currency2);
        $this->productPrice2->setPrice(9.99);
        $this->productPrice2->setProduct($this->product1);
        $this->product1->addPrice($this->productPrice2);

        $productTranslation1 = new ProductTranslation();
        $productTranslation1->setProduct($this->product1);
        $productTranslation1->setLocale('en');
        $productTranslation1->setName('EnglishProductTranslationName-1');
        $productTranslation1->setShortDescription('EnglishProductShortDescription-1');
        $productTranslation1->setLongDescription('EnglishProductLongDescription-1');

        $this->productAttribute1 = new ProductAttribute();
        $this->productAttribute1->setProduct($this->product1);
        $this->productAttribute1->setAttribute($this->attribute1);
        $this->productAttribute1->setAttributeValue($this->attributeValue1);

        // Product 2
        // Product Type
        $this->type2 = new Type();
        $this->type2->setTranslationKey('Type2');

        // AttributeSet
        $this->attributeSet2 = new AttributeSet();
        $this->attributeSetTranslation2 = new AttributeSetTranslation();
        $this->attributeSetTranslation2->setLocale('en');
        $this->attributeSetTranslation2->setName('EnglishTemplate-2');
        $this->attributeSetTranslation2->setAttributeSet($this->attributeSet2);

        // Attributes
        $this->attributeType2 = new AttributeType();
        $this->attributeType2->setName('EnglishAttributeType-2');
        $this->attribute2 = new Attribute();
        $this->attribute2->setCreated(new DateTime());
        $this->attribute2->setChanged(new DateTime());
        $this->attribute2->setType($this->attributeType2);
        $this->attribute2->setKey('key-2');

        // Attribute Translations
        $this->attributeTranslation2 = new AttributeTranslation();
        $this->attributeTranslation2->setAttribute($this->attribute2);
        $this->attributeTranslation2->setLocale('en');
        $this->attributeTranslation2->setName('EnglishAttribute-2');

        // Attribute Value
        $this->attributeValue2 = new AttributeValue();
        $this->attributeValue2->setAttribute($this->attribute2);

        // Attribute Value Translation
        $this->attributeValueTranslation2 = new AttributeValueTranslation();
        $this->attributeValueTranslation2->setLocale('en');
        $this->attributeValueTranslation2->setName('EnglishAttributeValue-2');
        $this->attributeValueTranslation2->setAttributeValue($this->attributeValue2);

        // Product
        $this->product2 = new Product();
        $this->product2->setNumber('ProductNumber-1');
        $this->product2->setManufacturer('EnglishManufacturer-2');
        $this->product2->setType($this->type2);
        $this->product2->setStatus($this->productStatusActive);
        $this->product2->setAttributeSet($this->attributeSet2);
        $this->product1->setParent($this->product2);

        $productTranslation2 = new ProductTranslation();
        $productTranslation2->setProduct($this->product2);
        $productTranslation2->setLocale('en');
        $productTranslation2->setName('EnglishProductTranslationName-2');
        $productTranslation2->setShortDescription('EnglishProductShortDescription-2');
        $productTranslation2->setLongDescription('EnglishProductLongDescription-2');
        $this->product2->addTranslation($productTranslation2);

        $this->productAttribute2 = new ProductAttribute();
        $this->productAttribute2->setProduct($this->product2);
        $this->productAttribute2->setAttribute($this->attribute2);
        $this->productAttribute2->setAttributeValue($this->attributeValue2);

        $metadata = $this->em->getClassMetadata(get_class(new TaxClass()));
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $this->taxClass1 = new TaxClass();
        $this->taxClass1->setId(TaxClass::STANDARD_TAX_RATE);
        $taxClassTranslation1 = new TaxClassTranslation();
        $taxClassTranslation1->setName('20%');
        $taxClassTranslation1->setLocale('en');
        $taxClassTranslation1->setTaxClass($this->taxClass1);

        $this->category1 = new Category();
        $this->category1->setLft(1);
        $this->category1->setRgt(2);
        $this->category1->setDepth(1);
        $this->category1->setDefaultLocale('en');
        $categoryTranslation1 = new CategoryTranslation();
        $categoryTranslation1->setLocale('en');
        $categoryTranslation1->setTranslation('Category 1');
        $categoryTranslation1->setCategory($this->category1);
        $this->category1->addTranslation($categoryTranslation1);

        $this->category2 = new Category();
        $this->category2->setLft(3);
        $this->category2->setRgt(4);
        $this->category2->setDepth(1);
        $this->category2->setDefaultLocale('en');
        $categoryTranslation2 = new CategoryTranslation();
        $categoryTranslation2->setLocale('en');
        $categoryTranslation2->setTranslation('Category 2');
        $categoryTranslation2->setCategory($this->category2);
        $this->category2->addTranslation($categoryTranslation2);

        $metadata = $this->em->getClassMetadata(get_class(new DeliveryStatus()));
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);

        $this->deliveryStatusAvailable = new DeliveryStatus();
        $this->deliveryStatusAvailable->setId(DeliveryStatus::AVAILABLE);
        $deliveryStatusAvailableTranslation = new DeliveryStatusTranslation();
        $deliveryStatusAvailableTranslation->setDeliveryStatus($this->deliveryStatusAvailable);
        $deliveryStatusAvailableTranslation->setLocale('en');
        $deliveryStatusAvailableTranslation->setName('available');
        $this->deliveryStatusAvailable->addTranslation($deliveryStatusAvailableTranslation);

        $this->specialPrice1 = new SpecialPrice();
        $this->specialPrice1->setPrice('56');
        $this->specialPrice1->setCurrency($this->currency1);
        $this->specialPrice1->setStartDate(new \DateTime());
        $this->specialPrice1->setEndDate(new \DateTime());

        $this->contactTestData = new ContactTestData($this->getContainer());
        $this->product1->setSupplier($this->contactTestData->accountSupplier);
        $this->product2->setSupplier($this->contactTestData->accountSupplier2);

        $this->tag1 = new Tag();
        $this->tag1->setName('Tag 1');
        $this->tag2 = new Tag();
        $this->tag2->setName('Tag 2');

        $this->em->persist($this->tag1);
        $this->em->persist($this->tag2);

        $this->addon = new Addon();
        $this->addon->setAddon($this->product2);
        $this->addon->setProduct($this->product1);

        $this->em->persist($this->addon);

        $this->product1->addAddon($this->addon);

        $this->addonPrice = new AddonPrice();
        $this->addonPrice->setCurrency($this->currency1);
        $this->addonPrice->setPrice('2222.0');
        $this->addonPrice->setAddon($this->addon);

        $this->em->persist($this->addonPrice);

        $this->addon->addAddonPrice($this->addonPrice);

        $this->em->persist($this->deliveryStatusAvailable);
        $this->em->persist($deliveryStatusAvailableTranslation);

        $this->em->persist($this->category1);
        $this->em->persist($this->category2);

        $this->em->persist($this->taxClass1);
        $this->em->persist($taxClassTranslation1);

        $this->em->persist($this->currency1);
        $this->em->persist($this->currency2);
        $this->em->persist($this->currency3);

        $this->em->persist($this->productStatusActive);
        $this->em->persist($this->productStatusInactive);
        $this->em->persist($this->productStatusChanged);
        $this->em->persist($this->productStatusTranslationActive);
        $this->em->persist($this->productStatusTranslationInactive);
        $this->em->persist($this->productStatusTranslationChanged);

        $this->em->persist($this->productPrice1);
        $this->em->persist($this->productPrice2);
        $this->em->persist($this->type1);
        $this->em->persist($this->attributeType1);
        $this->em->persist($this->attributeSet1);
        $this->em->persist($this->attributeSetTranslation1);
        $this->em->persist($this->attribute1);
        $this->em->persist($this->attributeTranslation1);
        $this->em->persist($this->attributeValue1);
        $this->em->persist($this->attributeValueTranslation1);
        $this->em->persist($this->product1);
        $this->em->persist($productTranslation1);
        $this->em->persist($this->productAttribute1);

        $this->em->persist($this->type2);
        $this->em->persist($this->attributeType2);
        $this->em->persist($this->attributeSet2);
        $this->em->persist($this->attributeSetTranslation2);
        $this->em->persist($this->attribute2);
        $this->em->persist($this->attributeTranslation2);
        $this->em->persist($this->attributeValue2);
        $this->em->persist($this->attributeValueTranslation2);
        $this->em->persist($this->product2);
        $this->em->persist($productTranslation2);
        $this->em->persist($this->productAttribute2);
        $this->em->persist($this->specialPrice1);
        $this->em->flush();
    }

    public function testGetProductAddon()
    {
        $this->client->request('GET', '/api/products/' . $this->product1->getId() . '/addons');
        $response = json_decode($this->client->getResponse()->getContent());
        $addons = $response->_embedded->addons;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertCount(1, $addons);

        $addon = $addons[0];

        $this->assertEquals($this->product2->getId(), $addon->addon->id);

        $this->assertCount(1, $addon->prices);
        $this->assertEquals($this->addonPrice->getId(), $addon->prices[0]->id);
    }

    public function testGetProductAddonFlat()
    {
        $this->client->request('GET', '/api/products/' . $this->product1->getId() . '/addons?flat=true');
        $response = json_decode($this->client->getResponse()->getContent());
        $addons = $response->_embedded->addons;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertCount(1, $addons);

        $addon = $addons[0];
        $this->assertEquals($this->addon->getId(), $addon->id);
        $this->assertEquals($this->product2->getTranslation('en')->getName(), $addon->addonName);
    }

    public function testPostProductAddon()
    {
        $data = [
            'addon' => $this->product2->getId(),
            'prices' => [
                [
                    'value' => 456,
                    'currency' => 'GBP',
                ],
                [
                    'value' => 123,
                    'currency' => 'EUR',
                ],
            ],
        ];

        $this->client->request('POST', '/api/products/' . $this->product1->getId() . '/addons', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $addon = $response->addon;

        $this->assertEquals($addon->id, $this->product2->getId());
        $this->assertCount(2, $response->prices);
    }

    public function testPutProductAddon()
    {
        $data = [
            'addon' => $this->product2->getId(),
            'prices' => [
                [
                    'value' => 456,
                    'currency' => 'GBP',
                ],
                [
                    'value' => 123,
                    'currency' => 'EUR',
                ],
            ],
        ];

        $this->client->request('POST', '/api/products/' . $this->product1->getId() . '/addons', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $addon = $response->addon;

        $this->assertEquals($addon->id, $this->product2->getId());
        $this->assertCount(2, $response->prices);
    }

    public function testDeleteProductAddonAction()
    {
        // Create addon
        $data = [
            'addon' => $this->product2->getId(),
            'prices' => [
                [
                    'value' => 456,
                    'currency' => 'GBP',
                ],
                [
                    'value' => 123,
                    'currency' => 'EUR',
                ],
            ],
        ];

        $this->client->request('POST', '/api/products/' . $this->product1->getId() . '/addons', $data);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent());
        $addon = $response->addon;

        $this->assertEquals($addon->id, $this->product2->getId());

        // Delete addon
        $this->client->request(
            'DELETE',
            '/api/products/' . $this->product1->getId() . '/addons/' . $this->product2->getId(),
            $data
        );

        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());

        // Check if it is deleted
        $this->client->request('GET', '/api/products/' . $this->product1->getId() . '/addons');
        $response = json_decode($this->client->getResponse()->getContent());
        $addons = $response->_embedded->addons;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertCount(0, $addons);
    }

    public function testDeleteAddonAction()
    {
        // Create addon
        $data = [
            'addon' => $this->product2->getId(),
            'prices' => [
                [
                    'value' => 456,
                    'currency' => 'GBP',
                ],
                [
                    'value' => 123,
                    'currency' => 'EUR',
                ],
            ],
        ];

        $this->client->request('POST', '/api/products/' . $this->product1->getId() . '/addons', $data);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent());
        $addon = $response->addon;

        $this->assertEquals($addon->id, $this->product2->getId());

        // Delete addon
        $this->client->request(
            'DELETE',
            '/api/addons/' . $response->id,
            $data
        );

        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());

        // Check if it is deleted
        $this->client->request('GET', '/api/products/' . $this->product1->getId() . '/addons');
        $response = json_decode($this->client->getResponse()->getContent());
        $addons = $response->_embedded->addons;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertCount(0, $addons);
    }
}
