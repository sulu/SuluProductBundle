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
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\CategoryBundle\Entity\CategoryTranslation;
use Sulu\Bundle\ProductBundle\Entity\Currency;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatus;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatusTranslation;
use Sulu\Bundle\ProductBundle\Entity\Product;
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\AttributeTranslation;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute;
use Sulu\Bundle\ProductBundle\Entity\SpecialPrice;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\StatusTranslation;
use Sulu\Bundle\ProductBundle\Entity\TaxClass;
use Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Entity\TypeTranslation;
use Sulu\Bundle\ProductBundle\Entity\AttributeSet;
use Sulu\Bundle\ProductBundle\Entity\AttributeSetTranslation;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Component\HttpKernel\Client;
use Sulu\Bundle\ProductBundle\Entity\AttributeType;

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
     * @var Client
     */
    private $client;

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


    public function setUp()
    {
        $this->em = $this->db('ORM')->getOm();
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
        $this->currency1->setCode('eur');

        $this->currency2 = new Currency();
        $this->currency2->setName('USD');
        $this->currency2->setNumber('2');
        $this->currency2->setCode('usd');

        $this->currency3 = new Currency();
        $this->currency3->setName('GBP');
        $this->currency3->setNumber('3');
        $this->currency3->setCode('gbp');

        // Product 1
        // product type
        $this->type1 = new Type();
        $this->typeTranslation1 = new TypeTranslation();
        $this->typeTranslation1->setLocale('en');
        $this->typeTranslation1->setName('EnglishProductType-1');
        $this->typeTranslation1->setType($this->type1);

        // product status
        $metadata = $this->em->getClassMetaData(get_class(new Status()));
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $this->productStatus1 = new Status();
        $this->productStatus1->setId(Status::ACTIVE);
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
        $this->attributeType1 = new AttributeType();
        $this->attributeType1->setName('EnglishAttributeType-1');

        $metadata = $this->em->getClassMetaData(get_class(new Attribute()));
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);

        $this->attribute1 = new Attribute();
        $this->attribute1->setId(Attribute::ATTRIBUTE_TYPE_TEXT);
        $this->attribute1->setCreated(new DateTime());
        $this->attribute1->setChanged(new DateTime());
        $this->attribute1->setType($this->attributeType1);

        // Attribute Translations
        $this->attributeTranslation1 = new AttributeTranslation();
        $this->attributeTranslation1->setAttribute($this->attribute1);
        $this->attributeTranslation1->setLocale('en');
        $this->attributeTranslation1->setName('EnglishAttribute-1');

        // product
        $this->product1 = new Product();
        $this->product1->setNumber('ProductNumber-1');
        $this->product1->setManufacturer('EnglishManufacturer-1');
        $this->product1->setType($this->type1);
        $this->product1->setStatus($this->productStatus1);
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
        $metadata = $this->em->getClassMetaData(get_class(new Status()));
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $this->productStatus2 = new Status();
        $this->productStatus2->setId(Status::CHANGED);
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
        $this->attributeType2 = new AttributeType();
        $this->attributeType2->setName('EnglishAttributeType-2');
        $this->attribute2 = new Attribute();
        $this->attribute2->setCreated(new DateTime());
        $this->attribute2->setChanged(new DateTime());
        $this->attribute2->setType($this->attributeType2);

        // Attribute Translations
        $this->attributeTranslation2 = new AttributeTranslation();
        $this->attributeTranslation2->setAttribute($this->attribute2);
        $this->attributeTranslation2->setLocale('en');
        $this->attributeTranslation2->setName('EnglishAttribute-2');

        // product
        $this->product2 = new Product();
        $this->product2->setNumber('ProductNumber-1');
        $this->product2->setManufacturer('EnglishManufacturer-2');
        $this->product2->setType($this->type2);
        $this->product2->setStatus($this->productStatus2);
        $this->product2->setAttributeSet($this->attributeSet2);
        $this->product1->setParent($this->product2);

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

        $metadata = $this->em->getClassMetaData(get_class(new TaxClass()));
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
        $categoryTranslation1 = new CategoryTranslation();
        $categoryTranslation1->setLocale('en');
        $categoryTranslation1->setTranslation('Category 1');
        $categoryTranslation1->setCategory($this->category1);
        $this->category1->addTranslation($categoryTranslation1);

        $this->category2 = new Category();
        $this->category2->setLft(3);
        $this->category2->setRgt(4);
        $this->category2->setDepth(1);
        $categoryTranslation2 = new CategoryTranslation();
        $categoryTranslation2->setLocale('en');
        $categoryTranslation2->setTranslation('Category 2');
        $categoryTranslation2->setCategory($this->category2);
        $this->category2->addTranslation($categoryTranslation2);

        $metadata = $this->em->getClassMetaData(get_class(new DeliveryStatus()));
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);

        $this->deliveryStatusAvailable = new DeliveryStatus();
        $this->deliveryStatusAvailable->setId(DeliveryStatus::AVAILABLE);
        $deliveryStatusAvailableTranslation = new DeliveryStatusTranslation();
        $deliveryStatusAvailableTranslation->setDeliveryStatus($this->deliveryStatusAvailable);
        $deliveryStatusAvailableTranslation->setLocale('en');
        $deliveryStatusAvailableTranslation->setName('available');
        $this->deliveryStatusAvailable->addTranslation($deliveryStatusAvailableTranslation);

        $this->specialPrice1 = new SpecialPrice();
        $this->specialPrice1->setPrice("56");
        $this->specialPrice1->setCurrency($this->currency1);
        $this->specialPrice1->setStart(new \DateTime());
        $this->specialPrice1->setEnd(new \DateTime());

        $this->em->persist($this->deliveryStatusAvailable);
        $this->em->persist($deliveryStatusAvailableTranslation);

        $this->em->persist($this->category1);
        $this->em->persist($this->category2);

        $this->em->persist($this->taxClass1);
        $this->em->persist($taxClassTranslation1);

        $this->em->persist($this->currency1);
        $this->em->persist($this->currency2);
        $this->em->persist($this->currency3);

        $this->em->persist($this->productPrice1);
        $this->em->persist($this->productPrice2);
        $this->em->persist($this->type1);
        $this->em->persist($this->attributeType1);
        $this->em->persist($this->typeTranslation1);
        $this->em->persist($this->attributeSet1);
        $this->em->persist($this->attributeSetTranslation1);
        $this->em->persist($this->productStatus1);
        $this->em->persist($this->productStatusTranslation1);
        $this->em->persist($this->attribute1);
        $this->em->persist($this->attributeTranslation1);
        $this->em->persist($this->product1);
        $this->em->persist($productTranslation1);
        $this->em->persist($this->productAttribute1);

        $this->em->persist($this->type2);
        $this->em->persist($this->attributeType2);
        $this->em->persist($this->typeTranslation2);
        $this->em->persist($this->attributeSet2);
        $this->em->persist($this->attributeSetTranslation2);
        $this->em->persist($this->productStatus2);
        $this->em->persist($this->productStatusTranslation2);
        $this->em->persist($this->attribute2);
        $this->em->persist($this->attributeTranslation2);
        $this->em->persist($this->product2);
        $this->em->persist($productTranslation2);
        $this->em->persist($this->productAttribute2);
        $this->em->persist($this->specialPrice1);
        $this->em->flush();
    }

    public function testGetById()
    {
        $this->client->request('GET', '/api/products/'.$this->product1->getId());
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('ProductNumber-1', $response['number']);
        $this->assertEquals('EnglishManufacturer-1', $response['manufacturer']);
        $this->assertEquals($this->type1->getId(), $response['type']['id']);
        $this->assertEquals('EnglishProductType-1', $response['type']['name']);
        $this->assertEquals($this->productStatus1->getId(), $response['status']['id']);
        $this->assertEquals('EnglishProductStatus-1', $response['status']['name']);
        $this->assertContains(
            array(
                'id' => $this->productPrice1->getId(),
                'price' => 14.99,
                'currency' => array(
                    'id' => $this->currency1->getId(),
                    'name' => 'EUR',
                    'number' => '1',
                    'code' => 'eur'
                ),
                'minimumQuantity' => 0
            ),
            $response['prices']
        );
        $this->assertContains(
            array(
                'id' => $this->productPrice2->getId(),
                'price' => 9.99,
                'currency' => array(
                    'id' => $this->currency2->getId(),
                    'name' => 'USD',
                    'number' => '2',
                    'code' => 'usd'
                ),
                'minimumQuantity' => 0
            ),
            $response['prices']
        );
    }

    public function testGetAll()
    {
        $this->client->request('GET', '/api/products', array('ids' => ''));
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->products;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $item = $items[0];
        $this->assertEquals('ProductNumber-1', $item->number);
        $this->assertEquals('EnglishManufacturer-1', $item->manufacturer);
        $this->assertEquals('EnglishProductType-1', $item->type->name);
        $this->assertEquals($this->productStatus1->getId(), $item->status->id);
        $this->assertEquals($this->type1->getId(), $item->type->id);

        $item = $items[1];
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
        $this->assertEquals('ProductNumber-1', $item->number);
        $this->assertEquals('EnglishManufacturer-1', $item->manufacturer);
        $this->assertEquals('EnglishProductType-1', $item->type);
        $this->assertEquals('EnglishProductStatus-1', $item->status);
        $this->assertEquals('EnglishProductType-1', $item->type);

        $item = $items[1];
        $this->assertEquals('ProductNumber-1', $item->number);
        $this->assertEquals('EnglishManufacturer-2', $item->manufacturer);
        $this->assertEquals('EnglishProductType-2', $item->type);
        $this->assertEquals('EnglishProductStatus-2', $item->status);
        $this->assertEquals('EnglishProductType-2', $item->type);
    }

    public function testGetByStatus()
    {
        $this->client->request('GET', '/api/products?status=' . $this->productStatus1->getId(), array('ids'=> ''));
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
        $this->client->request('GET', '/api/products?type=' . $this->type1->getId(), array('ids'=> ''));
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(1, count($response->_embedded->products));
        $this->assertEquals($this->product1->getManufacturer(), $response->_embedded->products[0]->manufacturer);
        $this->assertEquals($this->typeTranslation1->getName(), $response->_embedded->products[0]->type->name);
    }

    // FIXME existing prices get processed in the add callback
    public function testPut()
    {
        $this->markTestSkipped();
        $this->client->request(
            'PUT',
            '/api/products/'.$this->product1->getId(),
            array(
                'name' => 'EnglishProductTranslationNameNew-1',
                'number' => 'EvilNumber',
                'manufacturer' => 'EvilKnievel',
                'status' => array(
                    'id' => $this->productStatus1->getId()
                ),
                'type' => array(
                    'id' => $this->type1->getId()
                ),
                'taxClass' => array(
                    'id' => $this->taxClass1->getId()
                ),
                'prices' => array(
                    array(
                        'id' => $this->productPrice1->getId(),
                        'price' => 17.99,
                        'currency' => array(
                            'id' => $this->currency1->getId(),
                            'name' => 'EUR'
                        )
                    ),
                    array(
                        'price' => 12.99,
                        'currency' => array(
                            'id' => $this->currency3->getId(),
                            'name' => 'GBP'
                        )
                    )
                )
            )
        );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/products/'.$this->product1->getId());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('EnglishProductTranslationNameNew-1', $response['name']);
        $this->assertEquals('EvilNumber', $response['number']);
        $this->assertEquals('EvilKnievel', $response['manufacturer']);
        $this->assertEquals('20%', $response['taxClass']['name']);

        $this->assertContains(
            array(
                'id' => $this->productPrice1->getId(),
                'price' => 17.99,
                'currency' => array(
                    'id' => $this->currency1->getId(),
                    'name' => 'EUR',
                    'number' => '1',
                    'code' => 'eur'
                )
            ),
            $response['prices']
        );
        $this->assertContains(
            array(
                'currency' => array(
                    'id' => $this->currency2->getId(),
                    'name' => 'USD',
                    'number' => '2',
                    'code' => 'usd'

                )
            ),
            $response['prices']
        );
        $this->assertContains(
            array(
                'id' => $this->productPrice2->getId()+1,
                'price' => 12.99,
                'currency' => array(
                    'id' => $this->currency3->getId(),
                    'name' => 'GBP'
                )
            ),
            $response['prices']
        );
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

    public function testPutNotExistingParentProduct()
    {
        $this->client->request(
            'PUT',
            '/api/products/'.$this->product1->getId(),
            array(
                'number' => 1,
                'status' => array('id' => $this->productStatus1->getId()),
                'type' => array('id' => 1),
                'attributeSet' => array('id' => $this->attributeSet1->getId()),
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
        $this->markTestSkipped();
        $this->client->request(
            'PUT',
            '/api/products/'.$this->product1->getId(),
            array(
                'number' => 1,
                'status' => array('id' => $this->productStatus1->getId()),
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
            '/api/products/'.$this->product1->getId(),
            array('number' => 1, 'status' => array('id' => $this->productStatus1->getId()), 'type' => array('id' => 666))
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
            '/api/products/'.$this->product1->getId(),
            array('number' => 1, 'type' => array('id' => 1), 'status' => array('id' => 666))
        );

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Status" and the id "666" not found.',
            $response->message
        );
    }

    public function testPutWithCategories()
    {
        $this->client->request(
            'PUT',
            '/api/products/'.$this->product1->getId(),
            array(
                'number' => 1,
                'type' => array('id' => $this->type1->getId()),
                'status' => array('id' => $this->productStatus1->getId()),
                'categories' => array(array('id' => $this->category1->getId()), array('id' => $this->category2->getId())),
                'cost' => 99.9
            )
        );
        
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/products/'.$this->product1->getId());

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(99.9, $response->cost);
        $this->assertEquals('Category 1', $response->categories[0]->name);
        $this->assertEquals('Category 2', $response->categories[1]->name);
    }

    public function testPutProductAttribute()
    {
        $data = array(
            'id' => array('id' => $this->product1->getId()),
            'status' => array('id' => $this->productStatus1->getId()),
            'attributes' => array(
            	0 => array(
                	'attributeId' => $this->productAttribute1->getAttribute()->getId(),
                	'value' => $this->productAttribute1->getValue()
            	),
            	1 => array(
            		'attributeId' => $this->productAttribute2->getAttribute()->getId(),
            		'value' => $this->productAttribute2->getValue()
            	)
            )
        );

        $this->client->request('PUT', '/api/products/'.$this->product1->getId(), $data);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals('EnglishProductAttributeValue-1', $response->attributes[0]->value);
        $this->assertEquals('EnglishProductAttributeValue-2', $response->attributes[1]->value);
    }

    public function testPost($testParent = false)
    {
        $data = array(
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
            ),
            'taxClass' => array(
                'id' => $this->taxClass1->getId()
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

        $this->assertEquals('NUMBER:0815', $response->number);
        $this->assertEquals(666.66, $response->cost);
        $this->assertEquals('Preis Info', $response->priceInfo);
        $this->assertEquals($this->product1->getManufacturer(), $response->manufacturer);

        $this->assertEquals('EnglishProductStatus-1', $response->status->name);

        $this->assertEquals('EnglishProductType-1', $response->type->name);

        // $this->assertEquals($this->attributeSet1->getId(), $response->attributeSet->id);
        // $this->assertEquals('EnglishTemplate-1', $response->attributeSet->name);

        $this->assertEquals('20%', $response->taxClass->name);

        if ($testParent) {
            $this->assertEquals($this->product2->getId(), $response->parent->id);
        }
    }

    public function testPostWithParent()
    {
        $this->testPost(true);
    }

    public function testPostNoType()
    {
        $data = array(
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
        $this->markTestSkipped();
        $data = array(
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
        $this->client->request('DELETE', '/api/products/'.$this->product1->getId());
        $this->assertEquals('204', $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/products/'.$this->product1->getId());
        $this->assertEquals('404', $this->client->getResponse()->getStatusCode());
    }

    public function testParentFilter()
    {
        $this->client->request('GET', '/api/products?flat=true&parent=null');

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertCount(1, $response->_embedded->products);
        $this->assertEquals('ProductNumber-1', $response->_embedded->products[0]->number);
    }

    public function testTypeFilter()
    {
        $this->client->request('GET', '/api/products?flat=true&type='.$this->type1->getId());

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertCount(1, $response->_embedded->products);
        $this->assertEquals('ProductNumber-1', $response->_embedded->products[0]->number);
    }

    public function testAllTypeFilter()
    {
        $this->client->request('GET', '/api/products?flat=true&type='.$this->type1->getId().','.$this->type2->getId());

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertCount(2, $response->_embedded->products);
        $this->assertEquals('ProductNumber-1', $response->_embedded->products[0]->number);
        $this->assertEquals('ProductNumber-1', $response->_embedded->products[1]->number);
    }

    public function testPutSpecialPrice()
    {
        $data = array(
            'id' => array('id' => $this->product1->getId()),
            'status' => array('id' => $this->productStatus1->getId()),
            'specialPrices' => array(
                array(
                    'price' => $this->specialPrice1->getPrice(),
                    'start' => $this->specialPrice1->getStart()->format('Y-m-d h:i:s'),
                    'end' => $this->specialPrice1->getEnd()->format('Y-m-d h:i:s'),
                    'currency' => array("code" => $this->specialPrice1->getCurrency()->getCode())
            	)
            )
        );

        $this->client->request('PUT', '/api/products/'.$this->product1->getId(), $data);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals('56', $response->specialPrices[0]->price);
        $this->assertEquals('eur', $response->specialPrices[0]->currency->code);
    }
}

