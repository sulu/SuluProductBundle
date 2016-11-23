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
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Entity\SpecialPrice;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\StatusTranslation;
use Sulu\Bundle\ProductBundle\Entity\TaxClass;
use Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductException;
use Sulu\Bundle\ProductBundle\Tests\Resources\ContactTestData;
use Sulu\Bundle\TagBundle\Entity\Tag;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Component\HttpKernel\Client;

class ProductControllerTest extends SuluTestCase
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
     * @var Tag
     */
    private $tag2;

    /**
     * @var Client
     */
    private $client;

    /**
     * @param int $productId
     * @param string $locale
     *
     * @return string
     */
    public static function getGetUrlForProduct($productId, $locale = 'en')
    {
        return '/api/products/' . $productId . '?locale=' . $locale;
    }

    /**
     * Test setup.
     */
    public function setUp()
    {
        $this->em = $this->getEntityManager();
        $this->purgeDatabase();
        $this->setUpTestData();
        $this->client = $this->createAuthenticatedClient();
        $this->em->flush();
    }

    /**
     * Setup all test data.
     */
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
        // product type
        $this->type1 = new Type();
        $this->type1->setTranslationKey('Type1');

        // product status active
        $metadata = $this->em->getClassMetadata(Status::class);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $this->productStatusActive = new Status();
        $this->productStatusActive->setId(Status::ACTIVE);
        $this->productStatusTranslationActive = new StatusTranslation();
        $this->productStatusTranslationActive->setLocale('en');
        $this->productStatusTranslationActive->setName('EnglishProductStatus-Active');
        $this->productStatusTranslationActive->setStatus($this->productStatusActive);

        // product status inactive
        $metadata = $this->em->getClassMetadata(Status::class);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $this->productStatusInactive = new Status();
        $this->productStatusInactive->setId(Status::INACTIVE);
        $this->productStatusTranslationInactive = new StatusTranslation();
        $this->productStatusTranslationInactive->setLocale('en');
        $this->productStatusTranslationInactive->setName('EnglishProductStatus-Inactive');
        $this->productStatusTranslationInactive->setStatus($this->productStatusInactive);

        // product status changed
        $metadata = $this->em->getClassMetadata(Status::class);
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

        $metadata = $this->em->getClassMetadata(Attribute::class);
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

        // product
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
        // product type
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

        // product
        $this->product2 = new Product();
        $this->product2->setNumber('ProductNumber-1');
        $this->product2->setManufacturer('EnglishManufacturer-2');
        $this->product2->setType($this->type2);
        $this->product2->setStatus($this->productStatusActive);
        $this->product2->setAttributeSet($this->attributeSet2);
        $this->product2->setIsRecurringPrice(true);
        $this->product1->setParent($this->product2);

        $productTranslation2 = new ProductTranslation();
        $productTranslation2->setProduct($this->product2);
        $productTranslation2->setLocale('en');
        $productTranslation2->setName('EnglishProductTranslationName-2');
        $productTranslation2->setShortDescription('EnglishProductShortDescription-2');
        $productTranslation2->setLongDescription('EnglishProductLongDescription-2');

        $this->productAttribute2 = new ProductAttribute();
        $this->productAttribute2->setProduct($this->product2);
        $this->productAttribute2->setAttribute($this->attribute2);
        $this->productAttribute2->setAttributeValue($this->attributeValue2);

        $metadata = $this->em->getClassMetadata(TaxClass::class);
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

        $metadata = $this->em->getClassMetadata(DeliveryStatus::class);
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

    /**
     * Creates a new Product-Addon relation.
     *
     * @param ProductInterface $product
     * @param ProductInterface $addonProduct
     * @param float $price
     * @param Currency $currency
     */
    private function createAddon(ProductInterface $product, ProductInterface $addonProduct, $price, Currency $currency)
    {
        $addon = new Addon();
        $this->em->persist($addon);
        $addon->setAddon($addonProduct);
        $addon->setProduct($product);
        $product->addAddon($addon);

        $addonPrice = new AddonPrice();
        $this->em->persist($addonPrice);
        $addonPrice->setCurrency($currency);
        $addonPrice->setPrice($price);
        $addonPrice->setAddon($addon);
        $addon->addAddonPrice($addonPrice);
    }

    /**
     * Tests if sulu validation is working for get products.
     */
    public function testValidation()
    {
        // Check erroneous validation.
        $this->client->request('GET', '/api/products/' . $this->product1->getId());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        // Check successful validation.
        $this->client->request('GET', static::getGetUrlForProduct($this->product1->getId()));
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Tests get product by id.
     */
    public function testGetById()
    {
        $this->client->request('GET', static::getGetUrlForProduct($this->product1->getId()));
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('ProductNumber-1', $response['number']);
        $this->assertEquals('EnglishManufacturer-1', $response['manufacturer']);
        $this->assertEquals($this->type1->getId(), $response['type']['id']);
        $this->assertEquals($this->productStatusInactive->getId(), $response['status']['id']);
        $this->assertEquals('EnglishProductStatus-Inactive', $response['status']['name']);
        $this->assertContains(
            [
                'id' => $this->productPrice1->getId(),
                'price' => 14.99,
                'currency' => [
                    'id' => $this->currency1->getId(),
                    'name' => 'EUR',
                    'number' => '1',
                    'code' => 'EUR',
                ],
                'minimumQuantity' => 0,
            ],
            $response['prices']
        );
        $this->assertContains(
            [
                'id' => $this->productPrice2->getId(),
                'price' => 9.99,
                'currency' => [
                    'id' => $this->currency2->getId(),
                    'name' => 'USD',
                    'number' => '2',
                    'code' => 'USD',
                ],
                'minimumQuantity' => 0,
            ],
            $response['prices']
        );
    }

    /**
     * Tests property addons of get product by id.
     */
    public function testGetByIdWithAddon()
    {
        $this->createAddon($this->product1, $this->product2, 2.3, $this->currency1);

        $this->getEntityManager()->flush();

        $this->client->request('GET', static::getGetUrlForProduct($this->product1->getId()));
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        // Check if addon is delivered through api.
        $this->assertCount(1, $response['addons']);
        $addon = $response['addons'][0];
        $this->assertCount(1, $addon['addonPrices']);
        $this->assertEquals($this->product2->getId(), $addon['id']);

        // Check if price is part of api.
        $price = $addon['addonPrices'][0];
        $this->assertEquals(2.3, $price['price']);
        $this->assertEquals($this->currency1->getId(), $price['currency']['id']);
    }

    /**
     * Tests get all products.
     */
    public function testGetAll()
    {
        $this->client->request('GET', '/api/products', ['ids' => '']);
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->products;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $item = $items[0];
        $this->assertEquals('ProductNumber-1', $item->number);
        $this->assertEquals('EnglishManufacturer-1', $item->manufacturer);
        $this->assertEquals($this->productStatusInactive->getId(), $item->status->id);
        $this->assertEquals($this->type1->getId(), $item->type->id);
        $this->assertFalse($item->isRecurringPrice);

        $item = $items[1];
        $this->assertEquals('ProductNumber-1', $item->number);
        $this->assertEquals('EnglishManufacturer-2', $item->manufacturer);
        $this->assertEquals($this->productStatusActive->getId(), $item->status->id);
        $this->assertEquals($this->type2->getId(), $item->type->id);
        $this->assertTrue($item->isRecurringPrice);
    }

    /**
     * Tests products flat api.
     */
    public function testGetAllFlat()
    {
        $this->client->request('GET', '/api/products?flat=true');
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->products;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $item = $items[0];
        $this->assertEquals('ProductNumber-1', $item->number);
        $this->assertEquals('EnglishManufacturer-1', $item->manufacturer);
        $this->assertEquals($this->type1->getTranslationKey(), $item->type);
        $this->assertEquals('EnglishProductStatus-Inactive', $item->status);

        $item = $items[1];
        $this->assertEquals('ProductNumber-1', $item->number);
        $this->assertEquals('EnglishManufacturer-2', $item->manufacturer);
        $this->assertEquals($this->type2->getTranslationKey(), $item->type);
        $this->assertEquals('EnglishProductStatus-Active', $item->status);
    }

    /**
     * Tests getting products by a status id.
     */
    public function testGetByStatus()
    {
        $this->client->request(
            'GET',
            '/api/products?status=' . $this->productStatusInactive->getId(),
            ['ids' => '']
        );
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, count($response->_embedded->products));
        $this->assertEquals($this->product1->getManufacturer(), $response->_embedded->products[0]->manufacturer);
        $this->assertEquals(
            $this->productStatusTranslationInactive->getName(),
            $response->_embedded->products[0]->status->name
        );
    }

    /**
     * Tests getting products by a type id.
     */
    public function testGetByType()
    {
        $this->client->request('GET', '/api/products?type=' . $this->type1->getId(), ['ids' => '']);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, count($response->_embedded->products));
        $this->assertEquals($this->product1->getManufacturer(), $response->_embedded->products[0]->manufacturer);
        $this->assertEquals($this->type1->getId(), $response->_embedded->products[0]->type->id);
    }

    /**
     * Tests put of a product.
     */
    public function testPut()
    {
        $this->markTestSkipped();
        $this->client->request(
            'PUT',
            '/api/products/' . $this->product1->getId(),
            [
                'name' => 'EnglishProductTranslationNameNew-1',
                'number' => 'EvilNumber',
                'manufacturer' => 'EvilKnievel',
                'isRecurringPrice' => true,
                'status' => [
                    'id' => $this->productStatusActive->getId(),
                ],
                'type' => [
                    'id' => $this->type1->getId(),
                ],
                'taxClass' => [
                    'id' => $this->taxClass1->getId(),
                ],
                'prices' => [
                    [
                        'id' => $this->productPrice1->getId(),
                        'price' => 17.99,
                        'currency' => [
                            'id' => $this->currency1->getId(),
                            'name' => 'EUR',
                        ],
                    ],
                    [
                        'price' => 12.99,
                        'currency' => [
                            'id' => $this->currency3->getId(),
                            'name' => 'GBP',
                        ],
                    ],
                ],
            ]
        );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/products/' . $this->product1->getId());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('EnglishProductTranslationNameNew-1', $response['name']);
        $this->assertEquals('EvilNumber', $response['number']);
        $this->assertEquals('EvilKnievel', $response['manufacturer']);
        $this->assertEquals('20%', $response['taxClass']['name']);
        $this->assertTrue($response['isRecurringPrice']);

        $this->assertContains(
            [
                'id' => $this->productPrice1->getId(),
                'price' => 17.99,
                'currency' => [
                    'id' => $this->currency1->getId(),
                    'name' => 'EUR',
                    'number' => '1',
                    'code' => 'eur',
                ],
            ],
            $response['prices']
        );
        $this->assertContains(
            [
                'currency' => [
                    'id' => $this->currency2->getId(),
                    'name' => 'USD',
                    'number' => '2',
                    'code' => 'usd',

                ],
            ],
            $response['prices']
        );
        $this->assertContains(
            [
                'id' => $this->productPrice2->getId() + 1,
                'price' => 12.99,
                'currency' => [
                    'id' => $this->currency3->getId(),
                    'name' => 'GBP',
                ],
            ],
            $response['prices']
        );
    }

    /**
     * Tests put when product does not exist.
     */
    public function testPutNotExisting()
    {
        $this->client->request('PUT', '/api/products/666', ['code' => 'MissingProduct']);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Product" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Test put when parent product does not exist.
     */
    public function testPutNotExistingParentProduct()
    {
        $this->client->request(
            'PUT',
            '/api/products/' . $this->product1->getId(),
            [
                'number' => 1,
                'status' => ['id' => $this->productStatusActive->getId()],
                'type' => ['id' => 1],
                'attributeSet' => ['id' => $this->attributeSet1->getId()],
                'parent' => ['id' => 666],
            ]
        );

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Product" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Tests put when attribute set does not exist.
     */
    public function testPutNotExistingAttributeSet()
    {
        $this->markTestSkipped();
        $this->client->request(
            'PUT',
            '/api/products/' . $this->product1->getId(),
            [
                'number' => 1,
                'status' => ['id' => $this->productStatusActive->getId()],
                'type' => ['id' => 1],
                'attributeSet' => ['id' => 666],
            ]
        );

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:AttributeSet" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Tests put when type id does not exist.
     */
    public function testPutNotExistingType()
    {
        $this->client->request(
            'PUT',
            '/api/products/' . $this->product1->getId(),
            [
                'number' => 1,
                'status' => [
                    'id' => $this->productStatusActive->getId(),
                ],
                'type' => [
                    'id' => 666,
                ],
            ]
        );

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Type" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Tests put when status id does not exist.
     */
    public function testPutNotExistingStatus()
    {
        $this->client->request(
            'PUT',
            '/api/products/' . $this->product1->getId(),
            ['number' => 1, 'type' => ['id' => 1], 'status' => ['id' => 666]]
        );

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Status" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Tests put with adding new categories.
     */
    public function testPutWithCategories()
    {
        $this->client->request(
            'PUT',
            '/api/products/' . $this->product1->getId(),
            [
                'number' => 1,
                'type' => ['id' => $this->type1->getId()],
                'status' => ['id' => $this->productStatusActive->getId()],
                'categories' => [
                    [
                        'id' => $this->category1->getId(),
                    ],
                    [
                        'id' => $this->category2->getId(),
                    ],
                ],
                'cost' => 99.9,
            ]
        );

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', static::getGetUrlForProduct($this->product1->getId()));

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(99.9, $response->cost);
        $this->assertEquals('Category 1', $response->categories[0]->name);
        $this->assertEquals('Category 2', $response->categories[1]->name);
    }

    /**
     * Tests put with adding attributes.
     */
    public function testPutProductAttribute()
    {
        $data = [
            'id' => ['id' => $this->product1->getId()],
            'status' => ['id' => $this->productStatusActive->getId()],
            'attributes' => [
                0 => [
                    'attributeId' => $this->productAttribute1->getAttribute()->getId(),
                    'attributeValueName' => $this->attributeValueTranslation1->getName(),
                ],
                1 => [
                    'attributeId' => $this->productAttribute2->getAttribute()->getId(),
                    'attributeValueName' => $this->attributeValueTranslation2->getName(),
                ],
                2 => [
                    'attributeId' => $this->productAttribute2->getAttribute()->getId(),
                    'attributeValueName' => 0,
                ],
            ],
        ];

        $this->client->request('PUT', '/api/products/' . $this->product1->getId(), $data);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertCount(3, $response->attributes);
        $this->assertEquals('EnglishAttributeValue-1', $response->attributes[0]->attributeValueName);
        $this->assertEquals('EnglishAttributeValue-2', $response->attributes[1]->attributeValueName);
        $this->assertEquals('0', $response->attributes[2]->attributeValueName);
    }

    /**
     * Tests post of a new product.
     *
     * @param bool $testParent
     */
    public function testPost($testParent = false)
    {
        $data = [
            'name' => 'English Product',
            'shortDescription' => 'This is an english product.',
            'longDescription' => 'Indeed, it\'s a real english product.',
            'isRecurringPrice' => true,
            'number' => 'NUMBER:0815',
            'manufacturer' => $this->product1->getManufacturer(),
            'manufacturerCountry' => [
                'id' => $this->product1->getManufacturerCountry(),
            ],
            'cost' => 666.66,
            'priceInfo' => 'Preis Info',
            'status' => [
                'id' => Status::INACTIVE,
            ],
            'type' => [
                'id' => $this->type1->getId(),
            ],
            'attributeSet' => [
                'id' => $this->attributeSet1->getId(),
            ],
            'taxClass' => [
                'id' => $this->taxClass1->getId(),
            ],
            'tags' => [
                'Tag 1', 'Tag 2',
            ],
        ];

        if ($testParent) {
            $data['parent']['id'] = $this->product2->getId();
        }

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', static::getGetUrlForProduct($response->id));
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals('English Product', $response->name);
        $this->assertEquals('This is an english product.', $response->shortDescription);
        $this->assertEquals('Indeed, it\'s a real english product.', $response->longDescription);

        $this->assertEquals('NUMBER:0815', $response->number);
        $this->assertEquals(666.66, $response->cost);
        $this->assertEquals('Preis Info', $response->priceInfo);
        $this->assertEquals($this->product1->getManufacturer(), $response->manufacturer);

        $this->assertEquals('EnglishProductStatus-Inactive', $response->status->name);

        $this->assertEquals($this->type1->getId(), $response->type->id);

        $this->assertCount(2, $response->tags);
        $this->assertTrue($response->isRecurringPrice);

        $this->assertEquals('20%', $response->taxClass->name);

        if ($testParent) {
            $this->assertEquals($this->product2->getId(), $response->parent->id);
        }
    }

    /**
     * Tests post with a parent.
     */
    public function testPostWithParent()
    {
        $this->testPost(true);
    }

    /**
     * Tests post without type.
     */
    public function testPostNoType()
    {
        $data = [
            'number' => 'NUMBER:0815',
            'status' => $this->productStatusActive->getId(),
        ];

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('The "SuluProductBundle:Product"-entity requires a "type"-argument', $response->message);
    }

    /**
     * Tests post when type does not exist.
     */
    public function testPostNotExistingType()
    {
        $data = [
            'number' => 'NUMBER:0815',
            'status' => ['id' => $this->productStatusActive->getId()],
            'type' => ['id' => 666],
        ];

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Type" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Tests post with no status.
     */
    public function testPostNoStatus()
    {
        $data = [
            'number' => 'NUMBER:0815',
            'type' => ['id' => $this->type1->getId()],
        ];

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('The "SuluProductBundle:Product"-entity requires a "status"-argument', $response->message);
    }

    /**
     * Tests post when status id does not exist.
     */
    public function testPostNotExistingStatus()
    {
        $data = [
            'number' => 'NUMBER:0815',
            'status' => ['id' => 666],
            'type' => ['id' => $this->productStatusActive->getId()],
        ];

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Status" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Test post with a non existing parent product.
     */
    public function testPostNotExistingParentProduct()
    {
        $data = [
            'number' => 'NUMBER:0815',
            'status' => ['id' => $this->productStatusActive->getId()],
            'type' => ['id' => $this->productStatusActive->getId()],
            'parent' => ['id' => 666],
        ];

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Product" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Test post when attribute set does not exist.
     */
    public function testPostNotExistingAttributeSet()
    {
        $this->markTestSkipped();
        $data = [
            'number' => 'NUMBER:0815',
            'status' => ['id' => $this->productStatusActive->getId()],
            'type' => ['id' => $this->productStatusActive->getId()],
            'attributeSet' => ['id' => 666],
        ];

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:AttributeSet" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Test deleting a specific product.
     */
    public function testDeleteById()
    {
        $this->client->request('DELETE', '/api/products/' . $this->product1->getId());
        $this->assertEquals('204', $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', static::getGetUrlForProduct($this->product1->getId()));
        $this->assertEquals('404', $this->client->getResponse()->getStatusCode());
    }

    /**
     * Testing getting flat products by parent id.
     */
    public function testParentFilter()
    {
        $this->client->request('GET', '/api/products?flat=true&parent=null');

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertCount(1, $response->_embedded->products);
        $this->assertEquals('ProductNumber-1', $response->_embedded->products[0]->number);
    }

    /**
     * Test getting flat products by type.
     */
    public function testTypeFilter()
    {
        $this->client->request('GET', '/api/products?flat=true&type=' . $this->type1->getId());

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertCount(1, $response->_embedded->products);
        $this->assertEquals('ProductNumber-1', $response->_embedded->products[0]->number);
    }

    /**
     * Test getting flat products by multiple types.
     */
    public function testAllTypeFilter()
    {
        $this->client->request(
            'GET',
            '/api/products?flat=true&type=' . $this->type1->getId() . ',' . $this->type2->getId()
        );

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertCount(2, $response->_embedded->products);
        $this->assertEquals('ProductNumber-1', $response->_embedded->products[0]->number);
        $this->assertEquals('ProductNumber-1', $response->_embedded->products[1]->number);
    }

    /**
     * Test put wit special prices.
     */
    public function testPutSpecialPrice()
    {
        $data = [
            'id' => ['id' => $this->product1->getId()],
            'status' => ['id' => $this->productStatusActive->getId()],
            'specialPrices' => [
                [
                    'price' => $this->specialPrice1->getPrice(),
                    'start' => $this->specialPrice1->getStartDate()->format('Y-m-d h:i:s'),
                    'end' => $this->specialPrice1->getEndDate()->format('Y-m-d h:i:s'),
                    'currency' => ['code' => $this->specialPrice1->getCurrency()->getCode()],
                ],
            ],
            'tags' => [
                'Tag 1', 'Tag 2',
            ],
        ];

        $this->client->request('PUT', '/api/products/' . $this->product1->getId(), $data);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals('56', $response->specialPrices[0]->price);
        $this->assertEquals('EUR', $response->specialPrices[0]->currency->code);
        $this->assertCount(2, $response->tags);
    }

    /**
     * Test activating an invalid product.
     */
    public function testActivateInvalidProduct()
    {
        // Trying to delete supplier from product and at the same time trying to activate it
        $data = [
            'id' => ['id' => $this->product1->getId()],
            'status' => ['id' => Status::ACTIVE],
            'supplier' => [],
        ];

        $this->client->request('PUT', '/api/products/' . $this->product1->getId(), $data);
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(ProductException::PRODUCT_NOT_VALID, $response->code ? $response->code : null);

        // Trying to delete prices from product and at the same time trying to activate it
        $data = [
            'id' => ['id' => $this->product1->getId()],
            'status' => ['id' => Status::ACTIVE],
            'prices' => [],
        ];

        $this->client->request('PUT', '/api/products/' . $this->product1->getId(), $data);
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(ProductException::PRODUCT_NOT_VALID, $response->code ? $response->code : null);
    }

    /**
     * Test put product with search terms.
     */
    public function testPutSearchterms()
    {
        $productId = $this->product1->getId();

        // Get product data.
        $this->client->request('GET', static::getGetUrlForProduct($productId));
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        // Manipulate search Terms.
        $content = json_decode($this->client->getResponse()->getContent(), true);
        $content['searchTerms'] = 'searchterm1,searchTörm2, SeÄrchTerm';

        // Create put request.
        $this->client->request('PUT', '/api/products/' . $productId, $content);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('searchterm1,searchtörm2,seärchterm', $content['searchTerms']);
    }
}
