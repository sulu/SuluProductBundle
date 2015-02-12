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

use Doctrine\ORM\EntityManager;
use Sulu\Bundle\ProductBundle\Api\Product;
use Sulu\Bundle\ProductBundle\Entity\Product as ProductEntity;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\StatusTranslation;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Entity\TypeTranslation;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Bundle\FrameworkBundle\Client; 

class VariantControllerTest extends SuluTestCase
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
     * @var EntityManager
     */
    protected $em;

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
        $this->em = $this->db('ORM')->getOm();
        $this->purgeDatabase();
        $this->createFixtures();
        $this->client = $this->createAuthenticatedClient();
        $this->em->flush();
    }

    public function createFixtures()
    {
        $this->productType = new Type();
        $productTypeTranslation = new TypeTranslation();
        $productTypeTranslation->setLocale('en');
        $productTypeTranslation->setName('Product');
        $productTypeTranslation->setType($this->productType);
        $this->em->persist($this->productType);
        $this->em->persist($productTypeTranslation);

        $this->productWithVariantsType = new Type();
        $productWithVariantsTypeTranslation = new TypeTranslation();
        $productWithVariantsTypeTranslation->setLocale('en');
        $productWithVariantsTypeTranslation->setName('Product with Variants');
        $productWithVariantsTypeTranslation->setType($this->productWithVariantsType);

        $this->em->persist($this->productWithVariantsType);
        $this->em->persist($productWithVariantsTypeTranslation);

        $this->activeStatus = new Status();
        $activeStatusTranslation = new StatusTranslation();
        $activeStatusTranslation->setLocale('en');
        $activeStatusTranslation->setName('Active');
        $activeStatusTranslation->setStatus($this->activeStatus);

        $this->em->persist($this->activeStatus);
        $this->em->persist($activeStatusTranslation);

        $this->product = new Product(new ProductEntity(), 'en');
        $this->product->setName('Product with Variants');
        $this->product->setNumber('1');
        $this->product->setStatus($this->activeStatus);
        $this->product->setType($this->productWithVariantsType);

        $this->em->persist($this->product->getEntity());
    }

    public function testGetAll()
    {
        $productVariant1 = new Product(new ProductEntity(), 'en');
        $productVariant1->setName('Productvariant');
        $productVariant1->setNumber('2');
        $productVariant1->setStatus($this->activeStatus);
        $productVariant1->setType($this->productType);
        $productVariant1->setParent($this->product);
        $this->em->persist($productVariant1->getEntity());

        $productVariant2 = new Product(new ProductEntity(), 'en');
        $productVariant2->setName('Another Productvariant');
        $productVariant2->setNumber('3');
        $productVariant2->setStatus($this->activeStatus);
        $productVariant2->setType($this->productType);
        $productVariant2->setParent($this->product);
        $this->em->persist($productVariant2->getEntity());

        $anotherProduct = new Product(new ProductEntity(), 'en');
        $anotherProduct->setName('Another product');
        $anotherProduct->setNumber('4');
        $anotherProduct->setStatus($this->activeStatus);
        $anotherProduct->setType($this->productType);
        $this->em->persist($anotherProduct->getEntity());

        $this->em->flush();

        $this->client->request('GET', '/api/products/'.$this->product->getId().'/variants?flat=true');
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
        $this->em->persist($productVariant->getEntity());

        $this->em->flush();

        $this->client->request('POST', '/api/products/'.$this->product->getId().'/variants', array('id' => $productVariant->getId()));

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals('2', $response->number);
        $this->assertEquals('1', $response->parent->number);

        $this->client->request('GET', '/api/products/'.$productVariant->getId());

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
        $this->em->persist($productVariant->getEntity());

        $this->em->flush();

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
        $this->client->request('POST', '/api/products/'.$this->product->getId().'/variants', array('id' => 2));

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
        $this->em->persist($productVariant1->getEntity());

        $this->em->flush();

        $this->client->request('GET', '/api/products/'.$this->product->getId().'/variants/'.$productVariant1->getId());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('DELETE', '/api/products/'.$this->product->getId().'/variants/'.$productVariant1->getId());
        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/products/'.$this->product->getId().'/variants/'.$productVariant1->getId());
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }
}
