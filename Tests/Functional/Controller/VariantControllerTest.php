<?php
/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Tests\Functional\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use Sulu\Bundle\ProductBundle\Api\Product;
use Sulu\Bundle\ProductBundle\Entity\Product as ProductEntity;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\StatusTranslation;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Entity\TypeTranslation;
use Sulu\Bundle\TestBundle\Testing\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

class VariantControllerTest extends DatabaseTestCase
{
    protected static $entities;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Status
     */
    protected $activeStatus;

    /**
     * @var Type
     */
    protected $productType;

    /**
     * @var Product
     */
    private $product;

    /**
     * @var Type
     */
    protected $productWithVariantsType;

    public function setUp()
    {
        $this->client = static::createClient(
            array(),
            array(
                'PHP_AUTH_USER' => 'test',
                'PHP_AUTH_PW' => 'test'
            )
        );

        $this->setUpSchema();

        $this->productType = new Type();
        $productTypeTranslation = new TypeTranslation();
        $productTypeTranslation->setLocale('en');
        $productTypeTranslation->setName('Product');
        $productTypeTranslation->setType($this->productType);
        self::$em->persist($this->productType);
        self::$em->persist($productTypeTranslation);

        $this->productWithVariantsType = new Type();
        $productWithVariantsTypeTranslation = new TypeTranslation();
        $productWithVariantsTypeTranslation->setLocale('en');
        $productWithVariantsTypeTranslation->setName('Product with Variants');
        $productWithVariantsTypeTranslation->setType($this->productWithVariantsType);
        self::$em->persist($this->productWithVariantsType);
        self::$em->persist($productWithVariantsTypeTranslation);

        $this->activeStatus = new Status();
        $activeStatusTranslation = new StatusTranslation();
        $activeStatusTranslation->setLocale('en');
        $activeStatusTranslation->setName('Active');
        $activeStatusTranslation->setStatus($this->activeStatus);
        self::$em->persist($this->activeStatus);
        self::$em->persist($activeStatusTranslation);

        $this->product = new Product(new ProductEntity(), 'en');
        $this->product->setName('Product with Variants');
        $this->product->setNumber('1');
        $this->product->setStatus($this->activeStatus);
        $this->product->setType($this->productWithVariantsType);
        self::$em->persist($this->product->getEntity());

        self::$em->flush();
    }

    public function tearDown()
    {
        parent::tearDown();
        self::$tool->dropSchema(self::$entities);
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

    public function testGetAll()
    {
        $productVariant1 = new Product(new ProductEntity(), 'en');
        $productVariant1->setName('Productvariant');
        $productVariant1->setNumber('2');
        $productVariant1->setStatus($this->activeStatus);
        $productVariant1->setType($this->productType);
        $productVariant1->setParent($this->product);
        self::$em->persist($productVariant1->getEntity());

        $productVariant2 = new Product(new ProductEntity(), 'en');
        $productVariant2->setName('Another Productvariant');
        $productVariant2->setNumber('3');
        $productVariant2->setStatus($this->activeStatus);
        $productVariant2->setType($this->productType);
        $productVariant2->setParent($this->product);
        self::$em->persist($productVariant2->getEntity());

        $anotherProduct = new Product(new ProductEntity(), 'en');
        $anotherProduct->setName('Another product');
        $anotherProduct->setNumber('4');
        $anotherProduct->setStatus($this->activeStatus);
        $anotherProduct->setType($this->productType);
        self::$em->persist($anotherProduct->getEntity());

        self::$em->flush();

        $this->client->request('GET', '/api/products/1/variants?flat=true');
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(2, $response->total);
        $this->assertCount(2, $response->_embedded->products);
        $this->assertEquals('Productvariant', $response->_embedded->products[0]->name);
        $this->assertEquals('Another Productvariant', $response->_embedded->products[1]->name);
    }

    public function testPost()
    {
        $productVariant = new Product(new ProductEntity(), 'en');
        $productVariant->setName('ProductVariant');
        $productVariant->setNumber('2');
        $productVariant->setStatus($this->activeStatus);
        $productVariant->setType($this->productType);
        $productVariant->setParent($this->product);
        self::$em->persist($productVariant->getEntity());

        self::$em->flush();

        $this->client->request('POST', '/api/products/1/variants', array('id' => $productVariant->getId()));

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals('2', $response->number);
        $this->assertEquals('1', $response->parent->number);

        $this->client->request('GET', '/api/products/2');

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals('1', $response->parent->number);
    }

    public function testPostWithNotExistingParent()
    {
        $productVariant = new Product(new ProductEntity(), 'en');
        $productVariant->setName('ProductVariant');
        $productVariant->setNumber('2');
        $productVariant->setStatus($this->activeStatus);
        $productVariant->setType($this->productType);
        $productVariant->setParent($this->product);
        self::$em->persist($productVariant->getEntity());

        self::$em->flush();

        $this->client->request('POST', '/api/products/3/variants', array('id' => $productVariant->getId()));

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Product" and the id "3" not found.',
            $response->message
        );
    }

    public function testPostWithNotExistingVariant()
    {
        $this->client->request('POST', '/api/products/1/variants', array('id' => 2));

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Product" and the id "2" not found.',
            $response->message
        );
    }

    public function testDelete()
    {
        $productVariant1 = new Product(new ProductEntity(), 'en');
        $productVariant1->setName('Productvariant');
        $productVariant1->setNumber('2');
        $productVariant1->setStatus($this->activeStatus);
        $productVariant1->setType($this->productType);
        $productVariant1->setParent($this->product);
        self::$em->persist($productVariant1->getEntity());

        self::$em->flush();

        $this->client->request('GET', '/api/products/1/variants/2');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('DELETE', '/api/products/1/variants/2');
        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/products/1/variants/2');
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }
}
