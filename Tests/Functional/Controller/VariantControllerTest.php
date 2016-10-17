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
use Sulu\Bundle\ProductBundle\Api\ApiProductInterface;
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\Currency;
use Sulu\Bundle\ProductBundle\Entity\CurrencyRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\StatusTranslation;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Product\ProductFactoryInterface;
use Sulu\Bundle\ProductBundle\Tests\Resources\ProductTestData;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;

/**
 * This class contains tests for product variants.
 */
class VariantControllerTest extends SuluTestCase
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
     * @var ApiProductInterface
     */
    private $product;

    /**
     * @var Currency
     */
    private $currencyEUR;

    /**
     * @var Currency
     */
    private $currencyCHF;

    /**
     * @var Type
     */
    protected $productWithVariantsType;

    /**
     * @var ProductInterface[]
     */
    private $productVariants = [];

    /**
     * @var ProductTestData
     */
    private $productTestData;

    /**
     * @var Attribute
     */
    private $attribute1;

    /**
     * @var Attribute
     */
    private $attribute2;

    /**
     * @var array
     */
    private $testAttributeData;

    /**
     * @var array
     */
    private $testPriceData;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->em = $this->getEntityManager();
        $this->purgeDatabase();
        $this->createFixtures();
        $this->createTestData();
        $this->client = $this->createAuthenticatedClient();
    }

    /**
     * Setups test data, that is used for requests.
     */
    public function createTestData()
    {
        $this->testAttributeData = [
            [
                'attributeId' => $this->attribute1->getId(),
                'attributeValueName' => 'Attribute-1-Value-Name',
            ],
            [
                'attributeId' => $this->attribute2->getId(),
                'attributeValueName' => 'Attribute-2-Value-Name',
            ],
        ];
        $this->testPriceData = [
            [
                'price' => 17.99,
                'currency' => [
                    'id' => $this->currencyEUR->getId(),
                ],
            ],
            [
                'price' => 27.99,
                'currency' => [
                    'id' => $this->currencyCHF->getId(),
                ],
            ],
        ];
    }

    /**
     * Creates initial data for test.
     */
    public function createFixtures()
    {
        $this->productTestData = new ProductTestData($this->getContainer(), false);
        $this->attribute1 = $this->productTestData->createAttribute();
        $this->attribute2 = $this->productTestData->createAttribute();

        $this->currencyEUR = $this->getCurrencyRepository()->findByCode('EUR');
        $this->currencyCHF = $this->getCurrencyRepository()->findByCode('CHF');

        $this->productType = new Type();
        $this->productType->setTranslationKey('Type1');
        $this->em->persist($this->productType);

        $this->productWithVariantsType = new Type();
        $this->productWithVariantsType->setTranslationKey('Type2');
        $this->em->persist($this->productWithVariantsType);

        $this->activeStatus = new Status();
        $activeStatusTranslation = new StatusTranslation();
        $activeStatusTranslation->setLocale(self::REQUEST_LOCALE);
        $activeStatusTranslation->setName('Active');
        $activeStatusTranslation->setStatus($this->activeStatus);

        $this->em->persist($this->activeStatus);
        $this->em->persist($activeStatusTranslation);

        $this->product = $this->getProductFactory()->createApiEntity(
            $this->getProductFactory()->createEntity(),
            self::REQUEST_LOCALE
        );
        $productEntity = $this->product->getEntity();
        $this->product->setName('Product with Variants');
        $this->product->setNumber('1');
        $this->product->setStatus($this->activeStatus);
        $this->product->setType($this->productWithVariantsType);

        $productEntity->addVariantAttribute($this->attribute1);
        $productEntity->addVariantAttribute($this->attribute2);

        $this->em->persist($this->product->getEntity());

        $productVariant1 = $this->getProductFactory()->createApiEntity(
            $this->getProductFactory()->createEntity(),
            self::REQUEST_LOCALE
        );
        $productVariant1->setName('Productvariant');
        $productVariant1->setNumber('2');
        $productVariant1->setStatus($this->activeStatus);
        $productVariant1->setType($this->getVariantTypeReference());
        $productVariant1->setParent($this->product);
        $this->em->persist($productVariant1->getEntity());
        $this->productVariants[] = $productVariant1;

        $productVariant2 = $this->getProductFactory()->createApiEntity(
            $this->getProductFactory()->createEntity(),
            self::REQUEST_LOCALE
        );
        $productVariant2->setName('Another Productvariant');
        $productVariant2->setNumber('3');
        $productVariant2->setStatus($this->activeStatus);
        $productVariant2->setType($this->getVariantTypeReference());
        $productVariant2->setParent($this->product);
        $this->em->persist($productVariant2->getEntity());
        $this->productVariants[] = $productVariant2;

        $anotherProduct = $this->getProductFactory()->createApiEntity(
            $this->getProductFactory()->createEntity(),
            self::REQUEST_LOCALE
        );
        $anotherProduct->setName('Another product');
        $anotherProduct->setNumber('4');
        $anotherProduct->setStatus($this->activeStatus);
        $anotherProduct->setType($this->productType);
        $this->em->persist($anotherProduct->getEntity());

        $this->em->flush();
    }

    /**
     * Tests if all PUT validation attributes are checked.
     */
    public function testCGetValidation()
    {
        $this->client->request(
            'GET',
            '/api/products/' . $this->product->getId() . '/variants?flat=true'
        );

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $requiredAttributes = ['locale'];

        $this->assertValidationErrorAttributes($response, $requiredAttributes);
    }

    /**
     * Test flat get all variants of a product.
     */
    public function testGetAll()
    {
        $this->client->request(
            'GET',
            '/api/products/' . $this->product->getId() . '/variants?flat=true&locale=' . self::REQUEST_LOCALE
        );
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(2, $response->total);
        $this->assertCount(2, $response->_embedded->products);
        $this->assertEquals('Productvariant', $response->_embedded->products[0]->name);
        $this->assertEquals('Another Productvariant', $response->_embedded->products[1]->name);
    }

    /**
     * Tests if all POST validation attributes are checked.
     */
    public function testPostValidation()
    {
        $this->client->request(
            'POST',
            '/api/products/' . $this->product->getId() . '/variants',
            []
        );

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $requiredAttributes = ['locale', 'name', 'number', 'prices', 'attributes'];

        $this->assertValidationErrorAttributes($response, $requiredAttributes);
    }

    /**
     * Test post for creating a new product.
     */
    public function testPost()
    {
        $attributes = $this->testAttributeData;
        $prices = $this->testPriceData;

        // Perform post request.
        $responseObject = $this->performPostRequest($attributes, $prices);
        $this->assertEquals(200, $responseObject->getStatusCode());

        // Check response.
        $response = json_decode($responseObject->getContent(), true);

        $this->assertEquals('The new kid in town', $response['name']);
        $this->assertEquals('1234', $response['number']);
        $this->assertEquals($this->product->getId(), $response['parent']['id']);

        $this->assertAttributes($attributes, $response);

        $this->checkPrices($prices, $response);

        // Check if entity really is in database.
        $this->client->request('GET', ProductControllerTest::getGetUrlForProduct($response['id']));
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('The new kid in town', $response['name']);
    }

    /**
     * Test post when parent product does not exist.
     */
    public function testPostWithNotExistingParent()
    {
        $attributes = $this->testAttributeData;
        $prices = $this->testPriceData;
        $this->performPostRequest($attributes, $prices, 3);

        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Product" and the id "3" not found.',
            $response->message
        );
    }

    /**
     * Tests if all PUT validation attributes are checked.
     */
    public function testPutValidation()
    {
        $this->client->request(
            'PUT',
            '/api/products/' . $this->product->getId() . '/variants/' . $this->productVariants[0]->getId()
        );

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $requiredAttributes = ['locale', 'name', 'number', 'prices', 'attributes'];

        $this->assertValidationErrorAttributes($response, $requiredAttributes);
    }

    /**
     * Test PUT functionality of variants api.
     * First performs post to create data and then put to change it.
     */
    public function testPut()
    {
        $attributes = $this->testAttributeData;
        $prices = $this->testPriceData;

        // Perform POST request to create product variant.
        $responseObject = $this->performPostRequest($attributes, $prices);
        $this->assertEquals(200, $responseObject->getStatusCode());
        $response = json_decode($responseObject->getContent(), true);

        $attributes[0]['attributeValueName'] = 'Changed Attribute Value';
        $prices[0]['price'] = 10.01;

        // Perform PUT request to update product variant.
        $this->client->request(
            'PUT',
            sprintf(
                '/api/products/%s/variants/%s?locale=%s',
                $this->product->getId(),
                $response['id'],
                self::REQUEST_LOCALE
            ),
            [
                'name' => 'Not so new kid anymore',
                'number' => '4321',
                'attributes' => $attributes,
                'prices' => $prices,
            ]
        );

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('Not so new kid anymore', $response['name']);
        $this->assertEquals('4321', $response['number']);

        $this->assertAttributes($attributes, $response);
        $this->checkPrices($prices, $response);

        return $this->client->getResponse();
    }

    /**
     * Test deleting a product variant.
     */
    public function testDelete()
    {
        $this->client->request(
            'GET',
            '/api/products/' . $this->product->getId() . '/variants/' . $this->productVariants[1]->getId()
        );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'DELETE',
            '/api/products/' . $this->product->getId() . '/variants/' . $this->productVariants[1]->getId()
        );
        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'GET',
            '/api/products/' . $this->product->getId() . '/variants/' . $this->productVariants[1]->getId()
        );
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Test deleting a product variant.
     */
    public function testDeleteOfNonParent()
    {
        $this->client->request(
            'DELETE',
            '/api/products/' . $this->productVariants[0]->getId() . '/variants/' . $this->productVariants[1]->getId()
        );
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        $this->assertContains('Variant does not exists for given product.', $this->client->getResponse()->getContent());
    }

    /**
     * Performs a post request and returns response object.
     *
     * @param array $attributes
     * @param array $prices
     * @param null|int $parentId
     *
     * @return Response
     */
    private function performPostRequest(array $attributes, array $prices, $parentId = null)
    {
        // Fallback parent product.
        if (null === $parentId) {
            $parentId = $this->product->getId();
        }

        $this->client->request(
            'POST',
            '/api/products/' . $parentId . '/variants?locale=' . self::REQUEST_LOCALE,
            [
                'name' => 'The new kid in town',
                'number' => '1234',
                'attributes' => $attributes,
                'prices' => $prices,
            ]
        );

        return $this->client->getResponse();
    }

    /**
     * @return ProductFactoryInterface
     */
    private function getProductFactory()
    {
        return $this->getContainer()->get('sulu_product.product_factory');
    }

    /**
     * @return CurrencyRepository
     */
    private function getCurrencyRepository()
    {
        return $this->getContainer()->get('sulu_product.currency_repository');
    }

    /**
     * Compares attributes data with response data.
     *
     * @param array $attributes
     * @param array $response
     */
    private function assertAttributes(array $attributes, array $response)
    {
        // Check attributes.
        $this->assertCount(count($attributes), $response['attributes']);

        // Response order independent comparison of attributes:
        // For all given attributes compare with response data.
        foreach ($response['attributes'] as $responseAttribute) {
            $match = null;
            foreach ($attributes as $index => $attribute) {
                if ($attribute['attributeId'] === $responseAttribute['attributeId']) {
                    $match = $attribute;
                    unset($attributes[$index]);
                    break;
                }
            }
            $this->assertNotNull($match);
            $this->assertEquals($match['attributeValueName'], $responseAttribute['attributeValueName']);
        }
    }

    /**
     * Compares prices data with response data.
     *
     * @param array $prices
     * @param array $response
     */
    private function checkPrices(array $prices, array $response)
    {
        // Check prices.
        $this->assertCount(count($prices), $response['prices']);

        foreach ($response['prices'] as $responsePrice) {
            $match = null;
            foreach ($prices as $index => $price) {
                if ($price['currency']['id'] === $responsePrice['currency']['id']) {
                    $match = $price;
                    unset($prices[$index]);
                    break;
                }
            }
            $this->assertNotNull($match);
            $this->assertEquals($match['price'], $responsePrice['price']);
        }
    }

    /**
     * Returns the product type of a variant.
     *
     * @return Type
     */
    private function getVariantTypeReference()
    {
        return $this->getEntityManager()->getReference(Type::class, $this->retrieveTypeIdByKey('PRODUCT_VARIANT'));
    }

    /**
     * Maps product type string to its corresponding id.
     *
     * @param string $key
     *
     * @return int
     */
    private function retrieveTypeIdByKey($key)
    {
        $productTypesMap = $this->getContainer()->getParameter('sulu_product.product_types_map');

        return $productTypesMap[$key];
    }

    /**
     * @param array $validationResponse
     * @param array $requiredAttributes
     */
    private function assertValidationErrorAttributes(array $validationResponse, array $requiredAttributes)
    {
        $this->assertCount(count($requiredAttributes), $validationResponse);

        foreach ($validationResponse as $responseAttribute) {
            $this->assertContains($responseAttribute['property'], $requiredAttributes);
        }
    }
}
