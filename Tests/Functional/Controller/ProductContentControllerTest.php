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

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Tests\Resources\ProductTestData;
use Sulu\Bundle\RouteBundle\Entity\Route;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class ProductContentControllerTest extends SuluTestCase
{
    /**
     * @var ProductTestData
     */
    private $productTestData;

    /**
     * @var ProductInterface
     */
    private $product;

    /**
     * @var ProductTranslation
     */
    private $translation;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

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
     * Set up product test data.
     */
    private function setUpTestData()
    {
        $this->productTestData = new ProductTestData($this->getContainer(), false);

        // Set content title and route to product.
        $this->product = $this->productTestData->createProduct();
        $this->entityManager->flush();

        // Set title and routePath to translation.
        $this->translation = $this->product->getTranslation(ProductTestData::LOCALE);
        $this->translation->setContentTitle('Product Content Title');
        $this->translation->setRoute($this->createRouteForTranslation($this->translation, 'product-route-path'));

        $this->entityManager->flush();
    }

    /**
     * Creates a new route for product translation with given path.
     *
     * @param ProductTranslation $translation
     * @param string $path
     *
     * @return Route
     */
    private function createRouteForTranslation(ProductTranslation $translation, $path = '')
    {
        $route = new Route();
        $route->setEntityClass(get_class($translation));
        $route->setEntityId($translation->getId());
        $route->setLocale($translation->getLocale());
        $route->setPath($path);

        return $route;
    }

    /**
     * Test getting content of a product without providing locale.
     */
    public function testGetContentWithoutLocale()
    {
        // First try without locale.
        $this->client->request('GET', '/api/products/' . $this->product->getId() . '/content');
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Test getting content of a product.
     */
    public function testGetContent()
    {
        $this->client->request(
            'GET',
            '/api/products/' . $this->product->getId() . '/content?locale=' . ProductTestData::LOCALE
        );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('product-route-path', $response['routePath']);
        $this->assertEquals('Product Content Title', $response['title']);
    }

    /**
     * Test PUT content of a product without locale.
     */
    public function testPutContentWithoutLocale()
    {
        $this->client->request(
            'PUT',
            '/api/products/' . $this->product->getId() . '/content',
            [
                'routePath' => 'new-route-path',
                'title' => 'New Product Title',
            ]
        );
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Test PUT content of a product.
     */
    public function testPutContent()
    {
        $this->client->request(
            'PUT',
            '/api/products/' . $this->product->getId() . '/content?locale=' . ProductTestData::LOCALE,
            [
                'routePath' => 'new-route-path',
                'title' => 'New Product Title',
            ]
        );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);

        // Check response of put.
        $this->assertEquals('new-route-path', $response['routePath']);
        $this->assertEquals('New Product Title', $response['title']);

        // Now check get response.
        $this->client->request(
            'GET',
            '/api/products/' . $this->product->getId() . '/content?locale=' . ProductTestData::LOCALE
        );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('new-route-path', $response['routePath']);
        $this->assertEquals('New Product Title', $response['title']);
    }
}
