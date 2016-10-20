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
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\AttributeType;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Tests\Resources\ProductTestData;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Client;

class ProductVariantAttributeControllerTest extends SuluTestCase
{
    /**
     * @var string
     */
    const REQUEST_LOCALE = 'en';

    /**
     * @var array
     */
    protected static $entities;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var AttributeType
     */
    protected $attributeType2;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var ProductTestData
     */
    private $productTestData;

    /**
     * @var ProductInterface
     */
    private $product;

    /**
     * @var Attribute
     */
    private $attribute;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->entityManager = $this->getEntityManager();
        $this->purgeDatabase();
        $this->setUpTestData();
        $this->client = $this->createAuthenticatedClient();
        $this->entityManager->flush();
    }

    /**
     * Create initial data for tests.
     */
    private function setUpTestData()
    {
        $this->productTestData = new ProductTestData($this->getContainer(), true);

        $product = $this->productTestData->getProduct();
        $attribute1 = $this->productTestData->createAttribute(self::REQUEST_LOCALE);
        $attribute2 = $this->productTestData->createAttribute(self::REQUEST_LOCALE);

        $product->addVariantAttribute($attribute1);
        $product->addVariantAttribute($attribute2);

        // Add variantAttributes to another product to make test environment more complex.
        $product2 = $this->productTestData->getProduct2();
        $product2->addVariantAttribute($this->productTestData->createAttribute(self::REQUEST_LOCALE));

        $this->product = $this->productTestData->createProduct();
        $this->attribute = $this->productTestData->createAttribute(self::REQUEST_LOCALE);

        $this->entityManager->flush();
    }

    /**
     * Returns base path for receiving variantAttributes.
     *
     * @param int|null $productId
     *
     * @return string
     */
    private function getBasePath($productId = null)
    {
        if (!$productId) {
            $productId = $this->productTestData->getProduct()->getId();
        }

        return sprintf('/api/products/%s/variant-attributes', $productId);
    }

    /**
     * Test fields api.
     */
    public function testGetFields()
    {
        $this->client->request('GET', '/api/product-variant-attributes/fields?locale=' . static::REQUEST_LOCALE);
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertGreaterThanOrEqual(2, $response);
    }

    /**
     * Test cGET all available variant attributes for a certain product.
     */
    public function testGetAll()
    {
        $this->client->request('GET', $this->getBasePath() . '?locale=' . static::REQUEST_LOCALE);
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $attributes = $response['_embedded']['variantAttributes'];
        $this->assertCount(2, $attributes);
    }

    /**
     * Test cGET on a product that has no variant attributes assigned.
     */
    public function testGetEmptyResult()
    {
        // Product has no variant-attributes assigned, so the result is expected to be empty.
        $this->client->request(
            'GET',
            $this->getBasePath($this->product->getId()) . '?locale=' . static::REQUEST_LOCALE
        );
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $attributes = $response['_embedded']['variantAttributes'];
        $this->assertCount(0, $attributes);
    }

    /**
     * Test POST to create a new variant-attribute relation.
     */
    public function testPost()
    {
        $this->client->request(
            'POST',
            $this->getBasePath($this->product->getId()),
            [
                'attributeId' => $this->attribute->getId(),
            ]
        );
        $this->assertEquals(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'GET',
            $this->getBasePath($this->product->getId()) . '?locale=' . static::REQUEST_LOCALE
        );

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $attributes = $response['_embedded']['variantAttributes'];
        $this->assertCount(1, $attributes);
        $this->assertEquals($this->attribute->getId(), $attributes[0]['id']);
    }

    /**
     * Test POST with an already assigned variant attribute.
     */
    public function testPostExisting()
    {
        $this->product->addVariantAttribute($this->attribute);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            $this->getBasePath($this->product->getId()),
            [
                'attributeId' => $this->attribute->getId(),
            ]
        );
        $this->assertEquals(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'GET',
            $this->getBasePath($this->product->getId()) . '?locale=' . static::REQUEST_LOCALE
        );

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $attributes = $response['_embedded']['variantAttributes'];
        $this->assertCount(1, $attributes);
        $this->assertEquals($this->attribute->getId(), $attributes[0]['id']);
    }

    /**
     * Test DELETE to remove a variant-attribute relation.
     */
    public function testDelete()
    {
        $this->product->addVariantAttribute($this->attribute);
        $this->getEntityManager()->flush();

        $this->client->request(
            'DELETE',
            $this->getBasePath($this->product->getId()) . '/' . $this->attribute->getId(),
            []
        );
        $this->assertEquals(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Test DELETE on a non existing variant-attribute relation.
     */
    public function testDeleteNonExistingRelation()
    {
        $this->client->request(
            'DELETE',
            $this->getBasePath($this->product->getId()) . '/' . $this->attribute->getId(),
            []
        );
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }
}
