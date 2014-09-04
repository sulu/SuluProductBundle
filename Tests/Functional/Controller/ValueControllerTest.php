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
use Symfony\Component\HttpKernel\Client;

use Sulu\Bundle\ProductBundle\Api\Attribute;
use Sulu\Bundle\ProductBundle\Entity\Attribute as AttributeEntity;
use Sulu\Bundle\ProductBundle\Entity\AttributeTranslation;
use Sulu\Bundle\TestBundle\Entity\TestUser;
use Sulu\Bundle\TestBundle\Testing\DatabaseTestCase;
use Sulu\Bundle\ProductBundle\Entity\AttributeType;
use Sulu\Bundle\ProductBundle\Api\AttributeValue;
use Sulu\Bundle\ProductBundle\Entity\AttributeValue as AttributeValueEntity;

class ValueControllerTest extends DatabaseTestCase
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
        $this->testUser->setLocale('de');
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

        // **** Attribute types
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

        // **** Attributes
        $this->attributeEntity1 = new AttributeEntity();
        $this->attributeEntity1->setCreated(new DateTime());
        $this->attributeEntity1->setChanged(new DateTime());
        $this->attributeEntity1->setType($this->attributeType4);
        $this->attribute1 = new Attribute($this->attributeEntity1, 'en');
        $this->attribute1->setName('attribute-1');

        $this->attributeEntity2 = new AttributeEntity();
        $this->attributeEntity2->setCreated(new DateTime());
        $this->attributeEntity2->setChanged(new DateTime());
        $this->attributeEntity2->setType($this->attributeType2);
        $this->attribute2 = new Attribute($this->attributeEntity2, 'en');
        $this->attribute2->setName('attribute-2');

        // **** AttributeValues
        $this->attributeValueEntity1_1 = new AttributeValueEntity();
        $this->attributeValueEntity1_1->setSelected(true);
        $this->attributeValueEntity1_1->setAttribute($this->attributeEntity2);
        $this->attributeValue1_1 = new AttributeValue($this->attributeValueEntity1_1, 'en');
        $this->attributeValue1_1->setName('Value1_1');

        $this->attributeValueEntity1_2 = new AttributeValueEntity();
        $this->attributeValueEntity1_2->setSelected(false);
        $this->attributeValueEntity1_2->setAttribute($this->attributeEntity2);
        $this->attributeValue1_2 = new AttributeValue($this->attributeValueEntity1_2, 'en');
        $this->attributeValue1_2->setName('Value1_2');

        self::$em->persist($this->attributeType1);
        self::$em->persist($this->attribute1->getEntity());
        self::$em->persist($this->attributeValue1_1->getEntity());
        self::$em->persist($this->attributeValue1_2->getEntity());
        self::$em->persist($this->attributeType2);
        self::$em->persist($this->attribute2->getEntity());
        self::$em->persist($this->attributeType3);
        self::$em->persist($this->attributeType4);
        self::$em->persist($this->attributeType5);
        self::$em->flush();
    }

    private function setUpSchema()
    {
        self::$tool = new SchemaTool(self::$em);

        self::$entities = array(
            self::$em->getClassMetadata('Sulu\Bundle\TestBundle\Entity\TestUser'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\Attribute'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\AttributeTranslation'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\AttributeType'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\AttributeValue'),
            self::$em->getClassMetadata('Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation'),
        );

        self::$tool->dropSchema(self::$entities);
        self::$tool->createSchema(self::$entities);
    }

    public function tearDown()
    {
        parent::tearDown();
        self::$tool->dropSchema(self::$entities);
    }

    /**
     * Get a existing values for an attribute by it's id
     */
    public function testGetById()
    {
        $url = '/api/attributes/' .
            $this->attribute1->getId() .
            '/values/' .
            $this->attributeValue1_1->getId();
        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals('Value1_1', $response->name);
        $this->assertTrue($response->selected);
    }

    /**
     * Get all available attributes
     */
    public function testGetAll()
    {
        $url = '/api/attributes/' .
            $this->attribute1->getId() .
            '/values';
        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->attributeValues;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $item1 = $items[0];
        $this->assertEquals('Value1_1', $item1->name);
        $this->assertTrue($item1->selected);

        $item2 = $items[1];
        $this->assertEquals('Value1_2', $item2->name);
        $this->assertFalse($item2->selected);
    }

    /**
     * Get all available attribute values flat
     */
    public function testGetAllFlat()
    {
        $url = '/api/attributes/' .
            $this->attribute1->getId() .
            '/values?flat=true';
        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->attributeValues;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $item1 = $items[0];
        $this->assertEquals('Value1_1', $item1->name);
        $this->assertTrue($item1->selected);

        $item2 = $items[1];
        $this->assertEquals('Value1_2', $item2->name);
        $this->assertFalse($item2->selected);
    }

    /**
     * Post (create) and assign a new attribute value to an attribute
     */
    public function testPost()
    {
        $data = array(
            'name' => 'New value for attribute 2',
            'selected' => true
        );

        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values';

        $this->client->request('POST', $url, $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        // Get the new created value
        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values/' .
            $response->id;
        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals('New value for attribute 2', $response->name);
        $this->assertTrue($response->selected);
    }

    /**
     * Post with a missing attribute value selected defaults to false
     */
    public function testMissingSelectedData()
    {
        $data = array(
            'name' => 'New value for attribute 2'
        );

        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values';

        $this->client->request('POST', $url, $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        // Get the new created value
        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values/' .
            $response->id;
        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals('New value for attribute 2', $response->name);
        $this->assertFalse($response->selected);
    }

    /**
     * Post with a missing attribute value name does return an error
     */
    public function testPostMissingNameData()
    {
        $data = array(
            'selected' => true
        );

        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values';

        $this->client->request('POST', $url, $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'The "SuluProductBundle:AttributeValue"-entity requires a "name"-argument',
            $response->message
        );
    }

    /**
     * Put new name and selected to change the appropriate properties on an existing attribute value
     */
    public function testPut()
    {
        $data = array(
            'name' => 'New changed value for attribute 2',
            'selected' => false
        );

        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values/' .
            $this->attributeValue1_2->getId();

        $this->client->request('PUT', $url, $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        // Get the new created value
        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values/' .
            $this->attributeValue1_2->getId();
        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals('New changed value for attribute 2', $response->name);
        $this->assertFalse($response->selected);
    }

    /**
     * Put to a not existing attribute value id does return an error
     */
    public function testPutNotExisting()
    {
        $data = array(
            'name' => 'New changed value for attribute 2',
            'selected' => false
        );

        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values/' .
            666;

        $this->client->request('PUT', $url, $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'Entity with the type "SuluProductBundle:AttributeValue" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Put a new attribute value name does change the name of the attribute value for the given id
     */
    public function testPutNewName()
    {
        $data = array(
            'name' => 'New changed changed value for attribute 2'
        );

        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values/' .
            $this->attributeValue1_2->getId();

        $this->client->request('PUT', $url, $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        // Get the new created value
        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values/' .
            $this->attributeValue1_2->getId();
        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals('New changed changed value for attribute 2', $response->name);
        $this->assertFalse($response->selected);
    }

    /**
     * Put a new selected does change the selected attribute of the attribute value for the given id
     */
    public function testPutNewSelected()
    {
        $data = array(
            'selected' => true
        );

        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values/' .
            $this->attributeValue1_2->getId();

        $this->client->request('PUT', $url, $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        // Get the new created value
        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values/' .
            $this->attributeValue1_2->getId();
        $this->client->request('GET', $url);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->assertEquals('Value1_2', $response->name);
        $this->assertTrue($response->selected);
    }

    /**
     * Delete an existing attribute value
     */
    public function testDeleteById()
    {
        $url = '/api/attributes/' .
            $this->attribute2->getId() .
            '/values/' .
            $this->attributeValue1_2->getId();

        $this->client->request('DELETE', $url);
        $this->assertEquals('204', $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', $url);
        $this->assertEquals('404', $this->client->getResponse()->getStatusCode());
    }

    /**
     * Delete a not existing attribute value
     */
    public function testDeleteNotExistingById()
    {
        $this->client->request('DELETE', '/api/attributes/666');
        $this->assertEquals('404', $this->client->getResponse()->getStatusCode());
    }
}
