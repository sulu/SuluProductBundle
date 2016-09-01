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
use Sulu\Bundle\ProductBundle\Api\Attribute;
use Sulu\Bundle\ProductBundle\Api\AttributeValue;
use Sulu\Bundle\ProductBundle\Entity\Attribute as AttributeEntity;
use Sulu\Bundle\ProductBundle\Entity\AttributeType;
use Sulu\Bundle\ProductBundle\Entity\AttributeValue as AttributeValueEntity;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;

class ValueControllerTest extends SuluTestCase
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
     * @var string
     */
    protected static $baseUrl = '/api/attributes/%s/values%s';

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var AttributeType
     */
    private $attributeType1;

    /**
     * @var AttributeType
     */
    private $attributeType2;

    /**
     * @var AttributeType
     */
    private $attributeType3;

    /**
     * @var AttributeType
     */
    private $attributeType4;

    /**
     * @var AttributeType
     */
    private $attributeType5;

    /**
     * @var AttributeEntity
     */
    private $attributeEntity1;

    /**
     * @var AttributeEntity
     */
    private $attributeEntity2;

    /**
     * @var AttributeEntity
     */
    private $attributeEntity3;

    /**
     * @var Attribute
     */
    private $attribute1;

    /**
     * @var Attribute
     */
    private $attribute2;

    /**
     * @var Attribute
     */
    private $attribute3;

    /**
     * @var AttributeValueEntity
     */
    private $attributeValueEntity1_1;

    /**
     * @var AttributeValue
     */
    private $attributeValue1_1;

    /**
     * @var AttributeValueEntity
     */
    private $attributeValueEntity1_2;

    /**
     * @var AttributeValue
     */
    private $attributeValue1_2;

    /**
     * @var AttributeValueEntity
     */
    private $attributeValueEntity2_1;

    /**
     * @var AttributeValue
     */
    private $attributeValue2_1;

    /**
     * {@inheritdoc}
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
     * Create fixtures for test.
     */
    private function setUpTestData()
    {
        // Attribute types
        $this->attributeType1 = new AttributeType();
        $this->attributeType1->setName('product.attribute.type.text');
        $this->attributeType1->setId(1);

        $this->attributeType2 = new AttributeType();
        $this->attributeType2->setName('product.attribute.type.dropdown');
        $this->attributeType2->setId(2);

        $this->attributeType3 = new AttributeType();
        $this->attributeType3->setName('product.attribute.type.date');
        $this->attributeType3->setId(3);

        $this->attributeType4 = new AttributeType();
        $this->attributeType4->setName('product.attribute.type.checkbox');
        $this->attributeType4->setId(4);

        $this->attributeType5 = new AttributeType();
        $this->attributeType5->setName('product.attribute.type.radiobutton');
        $this->attributeType5->setId(5);

        // Attributes
        $this->attributeEntity1 = new AttributeEntity();
        $this->attributeEntity1->setCreated(new DateTime());
        $this->attributeEntity1->setChanged(new DateTime());
        $this->attributeEntity1->setType($this->attributeType4);
        $this->attribute1 = new Attribute($this->attributeEntity1, self::REQUEST_LOCALE, self::REQUEST_LOCALE);
        $this->attribute1->setName('attribute-1');
        $this->attribute1->setKey('key-1');

        $this->attributeEntity2 = new AttributeEntity();
        $this->attributeEntity2->setCreated(new DateTime());
        $this->attributeEntity2->setChanged(new DateTime());
        $this->attributeEntity2->setType($this->attributeType2);
        $this->attribute2 = new Attribute($this->attributeEntity2, self::REQUEST_LOCALE, self::REQUEST_LOCALE);
        $this->attribute2->setName('attribute-2');
        $this->attribute2->setKey('key-2');

        $this->attributeEntity3 = new AttributeEntity();
        $this->attributeEntity3->setCreated(new DateTime());
        $this->attributeEntity3->setChanged(new DateTime());
        $this->attributeEntity3->setType($this->attributeType3);
        $this->attribute3 = new Attribute($this->attributeEntity3, self::REQUEST_LOCALE, self::REQUEST_LOCALE);
        $this->attribute3->setName('attribute-3');
        $this->attribute3->setKey('key-3');

        // AttributeValues
        $this->attributeValueEntity1_1 = new AttributeValueEntity();
        $this->attributeValueEntity1_1->setAttribute($this->attributeEntity2);
        $this->attributeValue1_1 = new AttributeValue(
            $this->attributeValueEntity1_1,
            self::REQUEST_LOCALE,
            self::REQUEST_LOCALE
        );
        $this->attributeValue1_1->setName('Value1_1');

        $this->attributeValueEntity1_2 = new AttributeValueEntity();
        $this->attributeValueEntity1_2->setAttribute($this->attributeEntity2);
        $this->attributeValue1_2 = new AttributeValue(
            $this->attributeValueEntity1_2,
            self::REQUEST_LOCALE,
            self::REQUEST_LOCALE
        );
        $this->attributeValue1_2->setName('Value1_2');

        $this->attributeValueEntity2_1 = new AttributeValueEntity();
        $this->attributeValueEntity2_1->setAttribute($this->attributeEntity3);
        $this->attributeValue2_1 = new AttributeValue(
            $this->attributeValueEntity2_1,
            self::REQUEST_LOCALE,
            self::REQUEST_LOCALE
        );
        $this->attributeValue2_1->setName('Value2_1');

        $this->em->persist($this->attributeType1);
        $this->em->persist($this->attribute1->getEntity());
        $this->em->persist($this->attributeValue1_1->getEntity());
        $this->em->persist($this->attributeValue1_2->getEntity());
        $this->em->persist($this->attributeValue2_1->getEntity());
        $this->em->persist($this->attributeType2);
        $this->em->persist($this->attribute2->getEntity());
        $this->em->persist($this->attribute3->getEntity());
        $this->em->persist($this->attributeType3);
        $this->em->persist($this->attributeType4);
        $this->em->persist($this->attributeType5);
        $this->em->flush();
    }

    /**
     * Get a existing values for an attribute by it's id
     */
    public function testGetById()
    {
        $url = sprintf(static::$baseUrl, $this->attribute1->getId(), '/' . $this->attributeValue1_1->getId());

        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals('Value1_1', $response->name);
    }

    /**
     * Get not existing values for an attribute by it's id.
     */
    public function testGetNotExistingValueById()
    {
        $url = sprintf(static::$baseUrl, $this->attribute1->getId(), '/666');

        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:AttributeValue" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Get values from not existing attribute by it's id.
     */
    public function testGetNotExistingValueById2()
    {
        $url = sprintf(static::$baseUrl, '666', '/' . $this->attributeValue1_1->getId());

        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Attribute" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Get all available attributes.
     */
    public function testGetAll()
    {
        $url = sprintf(static::$baseUrl, $this->attribute2->getId(), '');

        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->attributeValues;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $item1 = $items[0];
        $this->assertEquals('Value1_1', $item1->name);

        $item2 = $items[1];
        $this->assertEquals('Value1_2', $item2->name);
    }

    /**
     * Get not existing all available attributes.
     */
    public function testNotExistingGetAll()
    {
        $url = sprintf(static::$baseUrl, '666', '');
        $this->client->request('GET', $url);
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    /**
     * Get all available attribute values flat.
     */
    public function testGetAllFlat()
    {
        $url = sprintf(static::$baseUrl, $this->attribute2->getId(), '?flat=true&locale=' . self::REQUEST_LOCALE);

        $this->client->request('GET', $url);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->attributeValues;

        $this->assertEquals(2, count($items));

        $item1 = $items[0];
        $this->assertEquals('Value1_1', $item1->name);

        $item2 = $items[1];
        $this->assertEquals('Value1_2', $item2->name);
    }

    /**
     * Post (create) and assign a new attribute value to an attribute.
     */
    public function testPost()
    {
        $data = [
            'name' => 'New value for attribute 2',
            'locale' => self::REQUEST_LOCALE,
        ];

        $url = sprintf(static::$baseUrl, $this->attribute2->getId(), '');

        $this->client->request('POST', $url, $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('New value for attribute 2', $response->name);

        // Get the new created value
        $url = sprintf(static::$baseUrl, $this->attribute2->getId(), '/' . $response->attributeValueId);

        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('New value for attribute 2', $response->name);
    }

    /**
     * Post with a missing attribute value name does return an error.
     */
    public function testPostMissingNameData()
    {
        $data = [];

        $url = sprintf(static::$baseUrl, $this->attribute2->getId(), '');

        $this->client->request('POST', $url, $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'The "SuluProductBundle:AttributeValue"-entity requires a "name"-argument',
            $response->message
        );
    }

    /**
     * Put new name to change the appropriate properties on an existing attribute value.
     */
    public function testPut()
    {
        $data = [
            'name' => 'New changed value for attribute 2',
        ];

        $url = sprintf(static::$baseUrl, $this->attribute2->getId(), '/' . $this->attributeValue1_2->getId());

        $this->client->request('PUT', $url, $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('New changed value for attribute 2', $response->name);

        // Get the new created value
        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values/' .
            $this->attributeValue1_2->getId();
        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('New changed value for attribute 2', $response->name);
    }

    /**
     * Put to a not existing attribute value id does return an error.
     */
    public function testPutNotExisting()
    {
        $data = [
            'name' => 'New changed value for attribute 2',
        ];

        $url = sprintf(static::$baseUrl, $this->attribute2->getId(), '/666');

        $this->client->request('PUT', $url, $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'Entity with the type "SuluProductBundle:AttributeValue" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Put a new attribute value name does change the name of the attribute value for the given id.
     */
    public function testPutNewName()
    {
        $data = [
            'name' => 'New changed changed value for attribute 2',
        ];

        $url = sprintf(static::$baseUrl, $this->attribute2->getId(), '/' . $this->attributeValue1_2->getId());

        $this->client->request('PUT', $url, $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('New changed changed value for attribute 2', $response->name);

        // Get the new created value
        $url = sprintf(static::$baseUrl, $this->attribute2->getId(), '/' . $this->attributeValue1_2->getId());

        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('New changed changed value for attribute 2', $response->name);
    }

    /**
     * Delete an existing attribute value.
     */
    public function testDeleteById()
    {
        $url = sprintf(static::$baseUrl, $this->attribute2->getId(), '/' . $this->attributeValue1_2->getId());

        $this->client->request('DELETE', $url);
        $this->assertEquals('204', $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', $url);
        $this->assertEquals('404', $this->client->getResponse()->getStatusCode());
    }

    /**
     * Delete a not existing attribute value.
     */
    public function testDeleteNotExistingById()
    {
        $this->client->request('DELETE', '/api/attributes/666');
        $this->assertEquals('404', $this->client->getResponse()->getStatusCode());
    }
}
