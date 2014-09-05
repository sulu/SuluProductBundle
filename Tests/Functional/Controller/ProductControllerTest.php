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

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        static::$kernel->getContainer()->set(
            'sulu_security.user_repository',
            static::$kernel->getContainer()->get('test_user_provider')
        );
    }
    public function setUp()
    {
        $this->setUpSchema();
        $this->setUpTestUser();
        $this->setUpTestData();
        $this->setUpClient();
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
        $this->typeTranslation1->setLocale('en');
        $this->typeTranslation1->setName('EnglishProductType-1');
        $this->typeTranslation1->setType($this->type1);

        // product status
        $this->productStatus1 = new Status();
        $this->productStatusTranslation1 = new StatusTranslation();
        $this->productStatusTranslation1->setLocale('en');
        $this->productStatusTranslation1->setName('EnglishProductStatus-1');
        $this->productStatusTranslation1->setStatus($this->productStatus1);

        // AttributeSet
        $this->attributeSet1 = new AttributeSet();
        $this->attributeSetTranslation1 = new AttributeSetTranslation();
        $this->attributeSetTranslation1->setLocale('en');
        $this->attributeSetTranslation1->setName('EnglishTemplate-1');
        $this->attributeSetTranslation1->setAttributeSet($this->attributeSet1);

        // Attributes
        $this->attribute1 = new Attribute();
        $this->attribute1->setCreated(new DateTime());
        $this->attribute1->setChanged(new DateTime());
        $this->attribute1->setUnit('EnglishAttributeUnit-1');
        $this->attribute1->setType('EnglishAttributeType-1');

        // Attribute Translations
        $this->attributeTranslation1 = new AttributeTranslation();
        $this->attributeTranslation1->setAttribute($this->attribute1);
        $this->attributeTranslation1->setLocale('en');
        $this->attributeTranslation1->setName('EnglishAttribute-1');

        // product
        $this->product1 = new Product();
        $this->product1->setCode('EnglishProductCode-1');
        $this->product1->setNumber('ProductNumber-1');
        $this->product1->setManufacturer('EnglishManufacturer-1');
        $this->product1->setType($this->type1);
        $this->product1->setStatus($this->productStatus1);
        $this->product1->setAttributeSet($this->attributeSet1);
        $this->product1->setCreated(new DateTime());
        $this->product1->setChanged(new DateTime());

        $productTranslation1 = new ProductTranslation();
        $productTranslation1->setProduct($this->product1);
        $productTranslation1->setLocale('en');
        $productTranslation1->setName('EnglishProductTranslationName-1');
        $productTranslation1->setShortDescription('EnglishProductShortDescription-1');
        $productTranslation1->setLongDescription('EnglishProductLongDescription-1');

        $this->productAttribute1 = new ProductAttribute();
        $this->productAttribute1->setValue('EnglishProductAttributeValue-1');
        $this->productAttribute1->setProduct($this->product1);
        $this->productAttribute1->setAttribute($this->attribute1);

        // Product 2
        // product type
        $this->type2 = new Type();
        $this->typeTranslation2 = new TypeTranslation();
        $this->typeTranslation2->setLocale('en');
        $this->typeTranslation2->setName('EnglishProductType-2');
        $this->typeTranslation2->setType($this->type2);

        // product status
        $this->productStatus2 = new Status();
        $this->productStatusTranslation2 = new StatusTranslation();
        $this->productStatusTranslation2->setLocale('en');
        $this->productStatusTranslation2->setName('EnglishProductStatus-2');
        $this->productStatusTranslation2->setStatus($this->productStatus2);

        // AttributeSet
        $this->attributeSet2 = new AttributeSet();
        $this->attributeSetTranslation2 = new AttributeSetTranslation();
        $this->attributeSetTranslation2->setLocale('en');
        $this->attributeSetTranslation2->setName('EnglishTemplate-2');
        $this->attributeSetTranslation2->setAttributeSet($this->attributeSet2);

        // Attributes
        $this->attribute2 = new Attribute();
        $this->attribute2->setCreated(new DateTime());
        $this->attribute2->setChanged(new DateTime());
        $this->attribute2->setUnit('EnglishAttributeUnit-2');
        $this->attribute2->setType('EnglishAttributeType-2');

        // Attribute Translations
        $this->attributeTranslation2 = new AttributeTranslation();
        $this->attributeTranslation2->setAttribute($this->attribute2);
        $this->attributeTranslation2->setLocale('en');
        $this->attributeTranslation2->setName('EnglishAttribute-2');

        // product
        $this->product2 = new Product();
        $this->product2->setCode('EnglishProductCode-2');
        $this->product2->setNumber('ProductNumber-1');
        $this->product2->setManufacturer('EnglishManufacturer-2');
        $this->product2->setType($this->type2);
        $this->product2->setStatus($this->productStatus2);
        $this->product2->setAttributeSet($this->attributeSet2);
        $this->product2->setCreated(new DateTime());
        $this->product2->setChanged(new DateTime());
        $this->product2->setParent($this->product1);

        $productTranslation2 = new ProductTranslation();
        $productTranslation2->setProduct($this->product2);
        $productTranslation2->setLocale('en');
        $productTranslation2->setName('EnglishProductTranslationName-2');
        $productTranslation2->setShortDescription('EnglishProductShortDescription-2');
        $productTranslation2->setLongDescription('EnglishProductLongDescription-2');

        $this->productAttribute2 = new ProductAttribute();
        $this->productAttribute2->setValue('EnglishProductAttributeValue-2');
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
        $this->assertEquals('EnglishProductCode-1', $response->code);
        $this->assertEquals('ProductNumber-1', $response->number);
        $this->assertEquals('EnglishManufacturer-1', $response->manufacturer);
        $this->assertEquals($this->type1->getId(), $response->type->id);
        $this->assertEquals('EnglishProductType-1', $response->type->name);
        $this->assertEquals($this->productStatus1->getId(), $response->status->id);
        $this->assertEquals('EnglishProductStatus-1', $response->status->name);
    }

    public function testGetAll()
    {
        $this->client->request('GET', '/api/products');
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->products;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $item = $items[0];
        $this->assertEquals('EnglishProductCode-1', $item->code);
        $this->assertEquals('ProductNumber-1', $item->number);
        $this->assertEquals('EnglishManufacturer-1', $item->manufacturer);
        $this->assertEquals('EnglishProductType-1', $item->type->name);
        $this->assertEquals($this->productStatus1->getId(), $item->status->id);
        $this->assertEquals($this->type1->getId(), $item->type->id);

        $item = $items[1];
        $this->assertEquals('EnglishProductCode-2', $item->code);
        $this->assertEquals('ProductNumber-1', $item->number);
        $this->assertEquals('EnglishManufacturer-2', $item->manufacturer);
        $this->assertEquals('EnglishProductType-2', $item->type->name);
        $this->assertEquals($this->productStatus2->getId(), $item->status->id);
        $this->assertEquals($this->type2->getId(), $item->type->id);
    }

    public function testGetAllFlat()
    {
        $this->client->request('GET', '/api/products?flat=true');
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->products;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $item = $items[0];
        $this->assertEquals('EnglishProductCode-1', $item->code);
        $this->assertEquals('ProductNumber-1', $item->number);
        $this->assertEquals('EnglishManufacturer-1', $item->manufacturer);
        $this->assertEquals('EnglishProductType-1', $item->type);
        $this->assertEquals('EnglishProductStatus-1', $item->status);
        $this->assertEquals('EnglishProductType-1', $item->type);

        $item = $items[1];
        $this->assertEquals('EnglishProductCode-2', $item->code);
        $this->assertEquals('ProductNumber-1', $item->number);
        $this->assertEquals('EnglishManufacturer-2', $item->manufacturer);
        $this->assertEquals('EnglishProductType-2', $item->type);
        $this->assertEquals('EnglishProductStatus-2', $item->status);
        $this->assertEquals('EnglishProductType-2', $item->type);
    }

    public function testGetByStatus()
    {
        $this->client->request('GET', '/api/products?status=' . $this->productStatus1->getId());
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, count($response->_embedded->products));
        $this->assertEquals($this->product1->getManufacturer(), $response->_embedded->products[0]->manufacturer);
        $this->assertEquals(
            $this->productStatusTranslation1->getName(),
            $response->_embedded->products[0]->status->name
        );
    }

    public function testGetByType()
    {
        $this->client->request('GET', '/api/products?type=' . $this->type1->getId());
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, count($response->_embedded->products));
        $this->assertEquals($this->product1->getManufacturer(), $response->_embedded->products[0]->manufacturer);
        $this->assertEquals($this->typeTranslation1->getName(), $response->_embedded->products[0]->type->name);
    }

    public function testGetByCode()
    {
        $this->client->request('GET', '/api/products?code=' . 'EnglishProductCode-1');
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, count($response->_embedded));
        $this->assertEquals($this->product1->getManufacturer(), $response->_embedded->products[0]->manufacturer);
        $this->assertEquals($this->product1->getCode(), $response->_embedded->products[0]->code);
    }

    public function testPut()
    {
        $this->client->request(
            'PUT',
            '/api/products/1',
            array(
                'name' => 'EnglishProductTranslationNameNew-1',
                'code' => 'EvilCode',
                'number' => 'EvilNumber',
                'manufacturer' => 'EvilKnievel',
                'status' => array(
                    'id' => $this->productStatus1->getId()
                ),
                'type' => array(
                    'id' => $this->type1->getId()
                ),
            )
        );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/products/1');
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('EnglishProductTranslationNameNew-1', $response->name);
        $this->assertEquals('EvilCode', $response->code);
        $this->assertEquals('EvilNumber', $response->number);
        $this->assertEquals('EvilKnievel', $response->manufacturer);
    }

    public function testPutNotExisting()
    {
        $this->client->request('PUT', '/api/products/666', array('code' => 'MissingProduct'));
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Product" and the id "666" not found.',
            $response->message
        );
    }

    public function testPutMissingNumber()
    {
        $this->client->request('PUT', '/api/products/1', array('number' => null));

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('The "SuluProductBundle:Product"-entity requires a "number"-argument', $response->message);
    }

    public function testPutNotExistingParentProduct()
    {
        $this->client->request(
            'PUT',
            '/api/products/1',
            array(
                'number' => 1,
                'status' => array('id' => 1),
                'type' => array('id' => 1),
                'attributeSet' => array('id' => 1),
                'parent' => array('id' => 666)
            )
        );

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Product" and the id "666" not found.',
            $response->message
        );
    }

    public function testPutNotExistingAttributeSet()
    {
        $this->client->request(
            'PUT',
            '/api/products/1',
            array(
                'number' => 1,
                'status' => array('id' => 1),
                'type' => array('id' => 1),
                'attributeSet' => array('id' => 666)
            )
        );

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:AttributeSet" and the id "666" not found.',
            $response->message
        );
    }

    public function testPutNotExistingType()
    {
        $this->client->request(
            'PUT',
            '/api/products/1',
            array('number' => 1, 'status' => array('id' => 1), 'type' => array('id' => 666))
        );

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Type" and the id "666" not found.',
            $response->message
        );
    }

    public function testPutNotExistingStatus()
    {
        $this->client->request(
            'PUT',
            '/api/products/1',
            array('number' => 1, 'type' => array('id' => 1), 'status' => array('id' => 666))
        );

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Status" and the id "666" not found.',
            $response->message
        );
    }

    public function testPost($testParent = false)
    {
        $data = array(
            'code' => 'CODE:0815',
            'name' => 'English Product',
            'shortDescription' => 'This is an english product.',
            'longDescription' => 'Indeed, it\'s a real english product.',
            'number' => 'NUMBER:0815',
            'manufacturer' => $this->product1->getManufacturer(),
            'manufacturerCountry' => array(
                'id' => $this->product1->getManufacturerCountry()
            ),
            'cost' => 666.66,
            'priceInfo' => 'Preis Info',
            'status' => array(
                'id' => $this->productStatus1->getId()
            ),
            'type' => array(
                'id' => $this->type1->getId()
            ),
            'attributeSet' => array(
                'id' => $this->attributeSet1->getId()
            )
        );

        if ($testParent) {
            $data['parent']['id'] = $this->product2->getId();
        }

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/products/' . $response->id);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals('English Product', $response->name);
        $this->assertEquals('This is an english product.', $response->shortDescription);
        $this->assertEquals('Indeed, it\'s a real english product.', $response->longDescription);

        $this->assertEquals('CODE:0815', $response->code);
        $this->assertEquals('NUMBER:0815', $response->number);
        $this->assertEquals(666.66, $response->cost);
        $this->assertEquals('Preis Info', $response->priceInfo);
        $this->assertEquals($this->product1->getManufacturer(), $response->manufacturer);

        $this->assertEquals('EnglishProductStatus-1', $response->status->name);

        $this->assertEquals('EnglishProductType-1', $response->type->name);

        $this->assertEquals($this->attributeSet1->getId(), $response->attributeSet->id);
        $this->assertEquals('EnglishTemplate-1', $response->attributeSet->name);

        if ($testParent) {
            $this->assertEquals($this->product2->getId(), $response->parent->id);
        }
    }

    public function testPostMissingNumber()
    {
        $data = array(
            'code' => 'CODE:0815',
            'manufacturer' => $this->product1->getManufacturer(),
            'manufacturerCountry' => $this->product1->getManufacturerCountry(),
            'cost' => 666.66,
            'status' => $this->productStatus1->getId(),
            'type' => $this->type1->getId(),
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
        $this->assertEquals('The "SuluProductBundle:Product"-entity requires a "type"-argument', $response->message);
    }

    public function testPostNotExistingType()
    {
        $data = array(
            'code' => 'CODE:0815',
            'number' => 'NUMBER:0815',
            'status' => array('id' => $this->productStatus1->getId()),
            'type' => array('id' => 666),
        );

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Type" and the id "666" not found.',
            $response->message
        );
    }

    public function testPostNoStatus()
    {
        $data = array(
            'code' => 'CODE:0815',
            'number' => 'NUMBER:0815',
            'type' => array('id' => $this->type1->getId())
        );

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('The "SuluProductBundle:Product"-entity requires a "status"-argument', $response->message);
    }

    public function testPostNotExistingStatus()
    {
        $data = array(
            'code' => 'CODE:0815',
            'number' => 'NUMBER:0815',
            'status' => array('id' => 666),
            'type' => array('id' => $this->productStatus1->getId()),
        );

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Status" and the id "666" not found.',
            $response->message
        );
    }

    public function testPostNotExistingParentProduct()
    {
        $data = array(
            'code' => 'CODE:0815',
            'number' => 'NUMBER:0815',
            'status' => array('id' => $this->productStatus1->getId()),
            'type' => array('id' => $this->productStatus1->getId()),
            'parent' => array('id' => 666)
        );

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Product" and the id "666" not found.',
            $response->message
        );
    }

    public function testPostNotExistingAttributeSet()
    {
        $data = array(
            'code' => 'CODE:0815',
            'number' => 'NUMBER:0815',
            'status' => array('id' => $this->productStatus1->getId()),
            'type' => array('id' => $this->productStatus1->getId()),
            'attributeSet' => array('id' => 666)
        );

        $this->client->request('POST', '/api/products', $data);
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:AttributeSet" and the id "666" not found.',
            $response->message
        );
    }

    public function testDeleteById()
    {
        $this->client->request('DELETE', '/api/products/1');
        $this->assertEquals('204', $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/products/1');
        $this->assertEquals('404', $this->client->getResponse()->getStatusCode());
    }

    public function testParentFilter()
    {
        $this->client->request('GET', '/api/products?flat=true&parent=null');

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertCount(1, $response->_embedded->products);
        $this->assertEquals('EnglishProductCode-1', $response->_embedded->products[0]->code);
    }
}
