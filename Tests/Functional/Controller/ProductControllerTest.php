<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Tests\Functional\Controller;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use Sulu\Bundle\ProductBundle\Entity\Product;
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\AttributeTranslation;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\StatusTranslation;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Entity\TypeTranslation;
use Sulu\Bundle\ProductBundle\Entity\AttributeSet;
use Sulu\Bundle\ProductBundle\Entity\AttributeSetTranslation;
use Sulu\Bundle\TestBundle\Entity\TestUser;
use Sulu\Bundle\TestBundle\Testing\DatabaseTestCase;
use Symfony\Component\HttpKernel\Client;


class ProductControllerTest extends DatabaseTestCase
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
     * @var TestUser
     */
    private $testUser;

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
    private $productStatus1;

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
     * @var StatusTranslation
     */
    private $productStatusTranslation1;

    /**
     * @var TypeTranslation
     */
    private $typeTranslation1;

    /**
     * @var AttributeSetTranslation
     */
    private $attributeSetTranslation1;

    /**
     * @var AttributeTranslation
     */
    private $attributeTranslation1;


    /**
     * @var Product
     */
    private $product2;

    /**
     * @var Type
     */
    private $type2;

    /**
     * @var Status
     */
    private $productStatus2;

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
     * @var StatusTranslation
     */
    private $productStatusTranslation2;

    /**
     * @var TypeTranslation
     */
    private $typeTranslation2;

    /**
     * @var AttributeSetTranslation
     */
    private $attributeSetTranslation2;

    /**
     * @var AttributeTranslation
     */
    private $attributeTranslation2;

    private static $languageCode1 = "en";
    private static $typeTranslationName1 = "EnglishProductType-1";
    private static $statusTranslationName1 = "EnglishProductStatus-1";
    private static $attributeSetTranslationName1 = "EnglishTemplate-1";
    private static $attributeTranslationName1 = "EnglishAttribute-1";
    private static $code1 = "EnglishProductCode-1";
    private static $number = "ProductNumber-1";
    private static $manufacturer1 = "EnglishManufacturer-1";
    private static $attributeUnit1 = "EnglishAttributeUnit-1";
    private static $attributeType1 = "EnglishAttributeType-1";
    private static $productAttributeValue1 = "EnglishProductAttributeValue-1";
    private static $productTranslationName1 = "EnglishProductTranslationName-1";
    private static $productShortDescription1 = "EnglishProductShortDescription-1";
    private static $productLongDescription1 = "EnglishProductLongDescription-1";

    private static $languageCode2 = "en";
    private static $typeTranslationName2 = "EnglishProductType-2";
    private static $statusTranslationName2 = "EnglishProductStatus-2";
    private static $attributeSetTranslationName2 = "EnglishTemplate-2";
    private static $attributeTranslationName2 = "EnglishAttribute-2";
    private static $code2 = "EnglishProductCode-2";
    private static $manufacturer2 = "EnglishManufacturer-2";
    private static $attributeUnit2 = "EnglishAttributeUnit-2";
    private static $attributeType2 = "EnglishAttributeType-2";
    private static $productAttributeValue2 = "EnglishProductAttributeValue-2";
    private static $productTranslationName2 = "EnglishProductTranslationName-2";
    private static $productShortDescription2 = "EnglishProductShortDescription-2";
    private static $productLongDescription2 = "EnglishProductLongDescription-2";


    public function setUp()
    {
        $this->setUpTestUser();
        $this->setUpClient();
        $this->setUpSchema();
        $this->setUpTestData();
    }

    private function setUpTestUser()
    {
        $this->testUser = new TestUser();
        $this->testUser->setUsername('test');
        $this->testUser->setPassword('test');
        $this->testUser->setLocale('en');
    }

    private function setUpClient()
    {
        $this->client = static::createClient(
            array(),
            array(
                'PHP_AUTH_USER' => $this->testUser->getUsername(),
                'PHP_AUTH_PW' => $this->testUser->getPassword()
            )
        );
    }

    private function setUpTestData()
    {
        // Product 1
        // product type
        $this->type1 = new Type();
        $this->typeTranslation1 = new TypeTranslation();
        $this->typeTranslation1->setLanguageCode(self::$languageCode1);
        $this->typeTranslation1->setName(self::$typeTranslationName1);
        $this->typeTranslation1->setType($this->type1);

        // product status
        $this->productStatus1 = new Status();
        $this->productStatusTranslation1 = new StatusTranslation();
        $this->productStatusTranslation1->setLanguageCode(self::$languageCode1);
        $this->productStatusTranslation1->setName(self::$statusTranslationName1);
        $this->productStatusTranslation1->setStatus($this->productStatus1);

        // AttributeSet
        $this->attributeSet1 = new AttributeSet();
        $this->attributeSetTranslation1 = new AttributeSetTranslation();
        $this->attributeSetTranslation1->setLanguageCode(self::$languageCode1);
        $this->attributeSetTranslation1->setName(self::$attributeSetTranslationName1);
        $this->attributeSetTranslation1->setAttributeSet($this->attributeSet1);

        // Attributes
        $this->attribute1 = new Attribute();
        $this->attribute1->setCreated(new DateTime());
        $this->attribute1->setChanged(new DateTime());
        $this->attribute1->setUnit(self::$attributeUnit1);
        $this->attribute1->setType(self::$attributeType1);

        // Attribute Translations
        $this->attributeTranslation1 = new AttributeTranslation();
        $this->attributeTranslation1->setAttribute($this->attribute1);
        $this->attributeTranslation1->setLanguageCode(self::$languageCode1);
        $this->attributeTranslation1->setName(self::$attributeTranslationName1);

        // product
        $this->product1 = new Product();
        $this->product1->setCode(self::$code1);
        $this->product1->setNumber(self::$number);
        $this->product1->setManufacturer(self::$manufacturer1);
        $this->product1->setType($this->type1);
        $this->product1->setStatus($this->productStatus1);
        $this->product1->setAttributeSet($this->attributeSet1);
        $this->product1->setCreated(new DateTime());
        $this->product1->setChanged(new DateTime());

        $productTranslation1 = new ProductTranslation();
        $productTranslation1->setProduct($this->product1);
        $productTranslation1->setLanguageCode(self::$languageCode1);
        $productTranslation1->setName(self::$productTranslationName1);
        $productTranslation1->setShortDescription(self::$productShortDescription1);
        $productTranslation1->setLongDescription(self::$productLongDescription1);

        $this->productAttribute1 = new ProductAttribute();
        $this->productAttribute1->setValue(self::$productAttributeValue1);
        $this->productAttribute1->setProduct($this->product1);
        $this->productAttribute1->setAttribute($this->attribute1);

        // Product 2
        // product type
        $this->type2 = new Type();
        $this->typeTranslation2 = new TypeTranslation();
        $this->typeTranslation2->setLanguageCode(self::$languageCode2);
        $this->typeTranslation2->setName(self::$typeTranslationName2);
        $this->typeTranslation2->setType($this->type2);

        // product status
        $this->productStatus2 = new Status();
        $this->productStatusTranslation2 = new StatusTranslation();
        $this->productStatusTranslation2->setLanguageCode(self::$languageCode2);
        $this->productStatusTranslation2->setName(self::$statusTranslationName2);
        $this->productStatusTranslation2->setStatus($this->productStatus2);

        // AttributeSet
        $this->attributeSet2 = new AttributeSet();
        $this->attributeSetTranslation2 = new AttributeSetTranslation();
        $this->attributeSetTranslation2->setLanguageCode(self::$languageCode2);
        $this->attributeSetTranslation2->setName(self::$attributeSetTranslationName2);
        $this->attributeSetTranslation2->setAttributeSet($this->attributeSet2);

        // Attributes
        $this->attribute2 = new Attribute();
        $this->attribute2->setCreated(new DateTime());
        $this->attribute2->setChanged(new DateTime());
        $this->attribute2->setUnit(self::$attributeUnit2);
        $this->attribute2->setType(self::$attributeType2);

        // Attribute Translations
        $this->attributeTranslation2 = new AttributeTranslation();
        $this->attributeTranslation2->setAttribute($this->attribute2);
        $this->attributeTranslation2->setLanguageCode(self::$languageCode2);
        $this->attributeTranslation2->setName(self::$attributeTranslationName2);

        // product
        $this->product2 = new Product();
        $this->product2->setCode(self::$code2);
        $this->product2->setNumber(self::$number);
        $this->product2->setManufacturer(self::$manufacturer2);
        $this->product2->setType($this->type2);
        $this->product2->setStatus($this->productStatus2);
        $this->product2->setAttributeSet($this->attributeSet2);
        $this->product2->setCreated(new DateTime());
        $this->product2->setChanged(new DateTime());

        $productTranslation2 = new ProductTranslation();
        $productTranslation2->setProduct($this->product2);
        $productTranslation2->setLanguageCode(self::$languageCode2);
        $productTranslation2->setName(self::$productTranslationName2);
        $productTranslation2->setShortDescription(self::$productShortDescription2);
        $productTranslation2->setLongDescription(self::$productLongDescription2);

        $this->productAttribute2 = new ProductAttribute();
        $this->productAttribute2->setValue(self::$productAttributeValue2);
        $this->productAttribute2->setProduct($this->product2);
        $this->productAttribute2->setAttribute($this->attribute2);

        self::$em->persist($this->type1);
        self::$em->persist($this->typeTranslation1);
        self::$em->persist($this->attributeSet1);
        self::$em->persist($this->attributeSetTranslation1);
        self::$em->persist($this->productStatus1);
        self::$em->persist($this->productStatusTranslation1);
        self::$em->persist($this->attribute1);
        self::$em->persist($this->attributeTranslation1);
        self::$em->persist($this->product1);
        self::$em->persist($productTranslation1);
        self::$em->persist($this->productAttribute1);

        self::$em->persist($this->type2);
        self::$em->persist($this->typeTranslation2);
        self::$em->persist($this->attributeSet2);
        self::$em->persist($this->attributeSetTranslation2);
        self::$em->persist($this->productStatus2);
        self::$em->persist($this->productStatusTranslation2);
        self::$em->persist($this->attribute2);
        self::$em->persist($this->attributeTranslation2);
        self::$em->persist($this->product2);
        self::$em->persist($productTranslation2);
        self::$em->persist($this->productAttribute2);
        self::$em->flush();
    }

    private function setUpSchema()
    {
        self::$tool = new SchemaTool(self::$em);

        self::$entities = array(
            self::$em->getClassMetadata('Sulu\Bundle\TestBundle\Entity\TestUser'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\Product'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\DeliveryStatus'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\ProductPrice'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\Type'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\TypeTranslation'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\Status'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\StatusTranslation'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\AttributeSet'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\AttributeSetTranslation'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\Attribute'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\AttributeTranslation'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\ProductTranslation'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\ProductAttribute'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\Addon'),

            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\Account'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\AccountCategory'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\Activity'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\ActivityStatus'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\Address'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\AddressType'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\BankAccount'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\Contact'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\ContactLocale'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\Country'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\Email'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\EmailType'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\Note'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\Fax'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\FaxType'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\Phone'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\PhoneType'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\Url'),
            self::$em->getClassMetadata('Sulu\Bundle\ContactBundle\Entity\UrlType'),
        );

        self::$tool->dropSchema(self::$entities);
        self::$tool->createSchema(self::$entities);
    }

    public function tearDown()
    {
        parent::tearDown();
        self::$tool->dropSchema(self::$entities);
    }

    public function testGetById()
    {
        $this->client->request('GET', '/api/products/1');
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(self::$code1, $response->code);
        $this->assertEquals(self::$number, $response->number);
        $this->assertEquals(self::$manufacturer1, $response->manufacturer);
        $this->assertEquals(self::$languageCode1, $response->type->translations[0]->languageCode);
        $this->assertEquals(self::$typeTranslationName1, $response->type->translations[0]->name);
        $this->assertEquals($this->productStatus1->getId(), $response->status->id);
        $this->assertEquals($this->type1->getId(), $response->type->id);
    }

    public function testGetAll()
    {
        $this->client->request('GET', '/api/products');
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $item = $items[0];
        $this->assertEquals(self::$code1, $item->code);
        $this->assertEquals(self::$number, $item->number);
        $this->assertEquals(self::$manufacturer1, $item->manufacturer);
        $this->assertEquals(self::$languageCode1, $item->type->translations[0]->languageCode);
        $this->assertEquals(self::$typeTranslationName1, $item->type->translations[0]->name);
        $this->assertEquals($this->productStatus1->getId(), $item->status->id);
        $this->assertEquals($this->type1->getId(), $item->type->id);

        $item = $items[1];
        $this->assertEquals(self::$code2, $item->code);
        $this->assertEquals(self::$number, $item->number);
        $this->assertEquals(self::$manufacturer2, $item->manufacturer);
        $this->assertEquals(self::$languageCode1, $item->type->translations[0]->languageCode);
        $this->assertEquals(self::$typeTranslationName2, $item->type->translations[0]->name);
        $this->assertEquals($this->productStatus2->getId(), $item->status->id);
        $this->assertEquals($this->type2->getId(), $item->type->id);
    }

    public function testGetByStatus()
    {
        $this->client->request('GET', '/api/products?status=' . $this->productStatus1->getId());
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, count($response->_embedded));
        $this->assertEquals($this->product1->getManufacturer(), $response->_embedded[0]->manufacturer);
        $this->assertEquals($this->productStatusTranslation1->getName(), $response->_embedded[0]->status->translations[0]->name);
    }

    public function testGetByType()
    {
        $this->client->request('GET', '/api/products?type=' . $this->type1->getId());
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, count($response->_embedded));
        $this->assertEquals($this->product1->getManufacturer(), $response->_embedded[0]->manufacturer);
        $this->assertEquals($this->typeTranslation1->getName(), $response->_embedded[0]->type->translations[0]->name);
    }

    public function testGetByCode()
    {
        $this->client->request('GET', '/api/products?code=' . self::$code1);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, count($response->_embedded));
        $this->assertEquals($this->product1->getManufacturer(), $response->_embedded[0]->manufacturer);
        $this->assertEquals($this->product1->getCode(), $response->_embedded[0]->code);
    }

    public function testGetByNumber()
    {
        $this->client->request('GET', '/api/products?number=' . self::$number);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($response->_embedded));
        $this->assertEquals($this->product1->getManufacturer(), $response->_embedded[0]->manufacturer);
        $this->assertEquals($this->product1->getNumber(), $response->_embedded[0]->number);
        $this->assertEquals($this->product2->getManufacturer(), $response->_embedded[1]->manufacturer);
        $this->assertEquals($this->product2->getNumber(), $response->_embedded[1]->number);
    }

    public function testPut()
    {
        $this->client->request(
            'PUT',
            '/api/products/1',
            array(
                'code' => 'EvilCode',
                'number' => 'EvilNumber',
                'manufacturer' => 'EvilKnievel'
            )
        );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/products/1');
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('EvilCode', $response->code);
        $this->assertEquals('EvilNumber', $response->number);
        $this->assertEquals('EvilKnievel', $response->manufacturer);
    }

    public function testPutNotExisting()
    {
        $this->client->request('PUT', '/api/products/666', array('code' => 'MissingProduct'));
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testPutMissingNumber()
    {
        $this->client->request('PUT', '/api/products/1', array('number' => null));
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    public function testPutNotExistingParentProduct()
    {
        $this->client->request('PUT', '/api/products/1', array('number' => 1, 'parent' => 666));

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('Entity with the type "SuluProductBundle:Product" and the id "666" not found.', $response->message);
    }

    public function testPutNotExistingAttributeSet()
    {
        $this->client->request('PUT', '/api/products/1', array('number' => 1, 'attributeSet' => 666));

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('Entity with the type "SuluProductBundle:AttributeSet" and the id "666" not found.', $response->message);
    }

    public function testPutNotExistingType()
    {
        $this->client->request('PUT', '/api/products/1', array('number' => 1, 'type' => 666));

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('Entity with the type "SuluProductBundle:Type" and the id "666" not found.', $response->message);
    }

    public function testPutNotExistingStatus()
    {
        $this->client->request('PUT', '/api/products/1', array('number' => 1, 'status' => 666));

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('Entity with the type "SuluProductBundle:Status" and the id "666" not found.', $response->message);
    }

    public function testPost($testParent = false)
    {
        $dateTime = new DateTime();

        $data = array(
            'code' => 'CODE:0815',
            'number' => 'NUMBER:0815',
            'manufacturer' => $this->product1->getManufacturer(),
            'manufacturerCountry' => $this->product1->getManufacturerCountry(),
            'created' => $dateTime,
            'changed' => $dateTime,
            'cost' => 666.66,
            'priceInfo' => 'Preis Info',
            'status' => $this->productStatus1->getId(),
            'type' => $this->type1->getId(),
            'attributeSet' => $this->attributeSet1->getId(),
            'translations' => array(
                array(
                    'languageCode' => $this->testUser->getLocale(),
                    'name' => 'English Product',
                    'shortDescription' => 'This is an english product.',
                    'longDescription' => 'Indeed, it\'s a real english product.',
                ),
            ),
            'attributes' => array(
                array(
                    'attribute' => array(
                        'id' => $this->attribute1->getId(),
                        'unit' => $this->attribute1->getUnit(),
                        'type' => $this->attribute1->getType(),
                        'created' => $this->attribute1->getCreated(),
                        'creator' => $this->attribute1->getCreator(),
                        'changed' => $this->attribute1->getChanged(),
                        'changer' => $this->attribute1->getChanger()
                    ),
                    'value' => 'Very product'
                ),
                array(
                    'attribute' => array(
                        'id' => $this->attribute2->getId(),
                        'unit' => $this->attribute2->getUnit(),
                        'type' => $this->attribute2->getType(),
                        'created' => $this->attribute2->getCreated(),
                        'creator' => $this->attribute2->getCreator(),
                        'changed' => $this->attribute2->getChanged(),
                        'changer' => $this->attribute2->getChanger()
                    ),
                    'value' => 'Much shiny'
                ),
            ),
        );

        if ($testParent) {
            $data['parent'] = $this->product2->getId();
        }

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/products/' . $response->id);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals('CODE:0815', $response->code);
        $this->assertEquals('NUMBER:0815', $response->number);
        $this->assertEquals(666.66, $response->cost);
        $this->assertEquals('Preis Info', $response->priceInfo);
        $this->assertEquals($this->product1->getManufacturer(), $response->manufacturer);

        $this->assertEquals($this->productStatus1->getId(), $response->status->id);
        $this->assertEquals($this->testUser->getLocale(), $response->status->translations[0]->languageCode);

        $this->assertEquals($this->type1->getId(), $response->type->id);
        $this->assertEquals($this->testUser->getLocale(), $response->type->translations[0]->languageCode);

        $this->assertEquals($this->attributeSet1->getId(), $response->attributeSet->id);
        $this->assertEquals($this->testUser->getLocale(), $response->attributeSet->translations[0]->languageCode);

        $translation = $response->translations[0];
        $this->assertEquals($this->testUser->getLocale(), $translation->languageCode);
        $this->assertEquals('English Product', $translation->name);
        $this->assertEquals('This is an english product.', $translation->shortDescription);
        $this->assertEquals('Indeed, it\'s a real english product.', $translation->longDescription);

        $productAttribute = $response->productAttributes[0];
        $attributeTranslation = $productAttribute->attribute->translations[0];
        $this->assertEquals('Very product', $productAttribute->value);
        $this->assertEquals($this->attribute1->getUnit(), $productAttribute->attribute->unit);
        $this->assertEquals($this->attribute1->getType(), $productAttribute->attribute->type);
        $this->assertEquals($this->attributeTranslation1->getName(), $attributeTranslation->name);
        $this->assertEquals($this->attributeTranslation1->getLanguageCode(), $attributeTranslation->languageCode);

        if ($testParent) {
            $this->assertEquals($this->product2->getId(), $response->parent->id);
        }
    }

    public function testPostMissingNumber()
    {
        $dateTime = new DateTime();

        $data = array(
            'code' => 'CODE:0815',
            'manufacturer' => $this->product1->getManufacturer(),
            'manufacturerCountry' => $this->product1->getManufacturerCountry(),
            'created' => $dateTime,
            'changed' => $dateTime,
            'cost' => 666.66,
            'priceInfo' => 'Preis Info',
            'status' => $this->productStatus1->getId(),
            'type' => $this->type1->getId(),
            'attributeSet' => $this->attributeSet1->getId(),
            'translations' => array(
                array(
                    'languageCode' => $this->testUser->getLocale(),
                    'name' => 'English Product',
                    'shortDescription' => 'This is an english product.',
                    'longDescription' => 'Indeed, it\'s a real english product.',
                ),
            ),
            'attributes' => array(
                array(
                    'attribute' => array(
                        'id' => $this->attribute1->getId(),
                        'unit' => $this->attribute1->getUnit(),
                        'type' => $this->attribute1->getType(),
                        'created' => $this->attribute1->getCreated(),
                        'creator' => $this->attribute1->getCreator(),
                        'changed' => $this->attribute1->getChanged(),
                        'changer' => $this->attribute1->getChanger()
                    ),
                    'value' => 'Very product'
                ),
                array(
                    'attribute' => array(
                        'id' => $this->attribute2->getId(),
                        'unit' => $this->attribute2->getUnit(),
                        'type' => $this->attribute2->getType(),
                        'created' => $this->attribute2->getCreated(),
                        'creator' => $this->attribute2->getCreator(),
                        'changed' => $this->attribute2->getChanged(),
                        'changer' => $this->attribute2->getChanger()
                    ),
                    'value' => 'Much shiny'
                ),
            ),
        );

        $this->client->request('POST', '/api/products', $data);
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    public function testPostWithParent()
    {
        $this->testPost(true);
    }

    public function testPostNoType()
{
    $data = array(
        'code' => 'CODE:0815',
        'number' => 'NUMBER:0815',
        'status' => $this->productStatus1->getId()
    );

    $this->client->request('POST', '/api/products', $data);
    $response = json_decode($this->client->getResponse()->getContent());

    $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    $this->assertEquals('There is no type for the product given', $response->message);
}

    public function testPostNotExistingType()
    {
        $data = array(
            'code' => 'CODE:0815',
            'number' => 'NUMBER:0815',
            'status' => $this->productStatus1->getId(),
            'type' => 666,
        );

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('Entity with the type "SuluProductBundle:Type" and the id "666" not found.', $response->message);
    }

    public function testPostNoStatus()
    {
        $data = array(
            'code' => 'CODE:0815',
            'number' => 'NUMBER:0815',
            'type' => $this->type1->getId()
        );

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('There is no status for the product given', $response->message);
    }

    public function testPostNotExistingStatus()
    {
        $data = array(
            'code' => 'CODE:0815',
            'number' => 'NUMBER:0815',
            'status' => 666,
            'type' => $this->productStatus1->getId(),
        );

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('Entity with the type "SuluProductBundle:Status" and the id "666" not found.', $response->message);
    }

    public function testPostNotExistingParentProduct()
    {
        $data = array(
            'code' => 'CODE:0815',
            'number' => 'NUMBER:0815',
            'status' => $this->productStatus1->getId(),
            'type' => $this->productStatus1->getId(),
            'parent' => 666
        );

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('Entity with the type "SuluProductBundle:Product" and the id "666" not found.', $response->message);
    }

    public function testPostNotExistingAttributeSet()
    {
        $data = array(
            'code' => 'CODE:0815',
            'number' => 'NUMBER:0815',
            'status' => $this->productStatus1->getId(),
            'type' => $this->productStatus1->getId(),
            'attributeSet' => 666
        );

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('Entity with the type "SuluProductBundle:AttributeSet" and the id "666" not found.', $response->message);
    }

    public function testDeleteById()
    {
        $this->client->request('DELETE', '/api/products/1');
        $this->assertEquals('204', $this->client->getResponse()->getStatusCode());
    }
}
