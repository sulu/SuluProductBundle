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
use Doctrine\ODM\PHPCR\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\Attributes\LoadAttributes;
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\AttributeTranslation;
use Sulu\Bundle\ProductBundle\Entity\AttributeType;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Component\HttpKernel\Client;

class AttributeControllerTest extends SuluTestCase
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
    protected $em;

    /**
     * @var AttributeType
     */
    protected $attributeType2;

    /**
     * @var AttributeType
     */
    private $attributeType1;

    /**
     * @var Attribute
     */
    private $attribute1;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Attribute
     */
    private $attribute2;

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
     * Create initial data for tests.
     */
    private function setUpTestData()
    {
        $metadata = $this->em->getClassMetadata(AttributeType::class);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);

        $this->attributeType1 = new AttributeType();
        $this->attributeType1->setId(1);
        $this->attributeType1->setName('some-translation-type-1-string');
        $this->attributeType2 = new AttributeType();
        $this->attributeType2->setName('some-translation-type-2-string');
        $this->attributeType2->setId(2);

        // shipping
        $metadata = $this->em->getClassMetadata(Attribute::class);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);

        $this->attribute1 = new Attribute();
        $this->attribute1->setId(Attribute::ATTRIBUTE_TYPE_TEXT);
        $this->attribute1->setCreated(new DateTime());
        $this->attribute1->setChanged(new DateTime());
        $this->attribute1->setType($this->attributeType1);
        $this->attribute1->setKey('key.one');
        $attributeTextTranslation = new AttributeTranslation();
        $attributeTextTranslation->setName('Gas');
        $attributeTextTranslation->setLocale('en');
        $attributeTextTranslation->setAttribute($this->attribute1);
        $this->attribute1->addTranslation($attributeTextTranslation);

        $this->attribute2 = new Attribute();
        $this->attribute2->setCreated(new DateTime());
        $this->attribute2->setChanged(new DateTime());
        $this->attribute2->setType($this->attributeType2);
        $this->attribute2->setKey('key.two');
        $attributeTextTranslation2 = new AttributeTranslation();
        $attributeTextTranslation2->setName('Power');
        $attributeTextTranslation2->setLocale('en');
        $attributeTextTranslation2->setAttribute($this->attribute2);
        $this->attribute2->addTranslation($attributeTextTranslation2);

        $this->em->persist($this->attributeType1);
        $this->em->persist($this->attribute1);
        $this->em->persist($this->attributeType2);
        $this->em->persist($this->attribute2);

        $this->em->flush();
    }

    /**
     * Get a existing attribute by it's id.
     */
    public function testGetById()
    {
        $this->client->request('GET', '/api/attributes/1');
        $response = json_decode($this->client->getResponse()->getContent());

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('some-translation-type-1-string', $response->type->name);
        $this->assertEquals('Gas', $response->name);
    }

    /**
     * Get a not existing attribute by it's id.
     */
    public function testGetNotExistingById()
    {
        $this->client->request('GET', '/api/attributes/666');
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
        $this->client->request('GET', '/api/attributes');
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->attributes;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $item1 = $items[0];
        $this->assertEquals('some-translation-type-1-string', $item1->type->name);
        $this->assertEquals('Gas', $item1->name);

        $item2 = $items[1];
        $this->assertEquals('some-translation-type-2-string', $item2->type->name);
        $this->assertEquals('Power', $item2->name);
    }

    /**
     * Get all available attributes flat.
     */
    public function testGetAllFlat()
    {
        $this->client->request('GET', '/api/attributes?flat=true');
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->attributes;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $item1 = $items[0];
        $this->assertEquals('some-translation-type-1-string', $item1->type);
        $this->assertEquals('Gas', $item1->name);

        $item2 = $items[1];
        $this->assertEquals('some-translation-type-2-string', $item2->type);
        $this->assertEquals('Power', $item2->name);
    }

    /**
     * Post (create) a new attribute.
     */
    public function testPost()
    {
        $data = [
            'name' => 'Material',
            'key' => 'key.one',
            'type' => [
                'id' => $this->attributeType1->getId(),
            ],
            'locale' => self::REQUEST_LOCALE,
        ];

        $this->client->request('POST', '/api/attributes', $data);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals('Material', $response->name);
        $this->assertEquals($this->attributeType1->getId(), $response->type->id);

        $this->client->request('GET', '/api/attributes/' . $response->id);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('Material', $response->name);
        $this->assertEquals('key.one', $response->key);
        $this->assertEquals($this->attributeType1->getId(), $response->type->id);
    }

    /**
     * Post with an invalid attribute type does not create a new attribute.
     */
    public function testPostInvalidType()
    {
        $data = [
            'name' => 'InvalidType',
            'type' => [
                'id' => 666,
            ],
            'locale' => self::REQUEST_LOCALE,
        ];

        $this->client->request('POST', '/api/attributes', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'Entity with the type "SuluProductBundle:AttributeType" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Post with a missing attribute name does not create a new attribute.
     */
    public function testPostMissingNameData()
    {
        $data = [
            'type' => [
                'id' => 1,
            ],
            'locale' => self::REQUEST_LOCALE,
        ];

        $this->client->request('POST', '/api/attributes', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'The "SuluProductBundle:Attribute"-entity requires a "name"-argument',
            $response->message
        );
    }

    /**
     * Post with a missing type object does not create a new attribute.
     */
    public function testPostMissingTypeData()
    {
        $data = [
            'name' => 'Some name',
            'locale' => self::REQUEST_LOCALE,
        ];

        $this->client->request('POST', '/api/attributes', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'The "SuluProductBundle:Attribute"-entity requires a "type"-argument',
            $response->message
        );
    }

    /**
     * Post with a missing type id does not create a new attribute.
     */
    public function testPostMissingTypeIdData()
    {
        $data = [
            'name' => 'InvalidTypeId',
            'type' => [],
            'locale' => self::REQUEST_LOCALE,
        ];

        $this->client->request('POST', '/api/attributes', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'The "SuluProductBundle:Attribute"-entity requires a "id"-argument',
            $response->message
        );
    }

    /**
     * Put new name and type to change the appropriate properties on an existing attribute.
     */
    public function testPut()
    {
        $data = [
            'name' => 'Petrol',
            'type' => [
                'id' => $this->attributeType2->getId(),
            ],
            'locale' => self::REQUEST_LOCALE,
        ];

        $this->client->request('PUT', '/api/attributes/1', $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('some-translation-type-2-string', $response->type->name);
        $this->assertEquals('Petrol', $response->name);

        $this->client->request('GET', '/api/attributes/1');
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('some-translation-type-2-string', $response->type->name);
        $this->assertEquals('Petrol', $response->name);
    }

    /**
     * Put to a not existing attribute id does return an error.
     */
    public function testPutNotExisting()
    {
        $data = [
            'name' => 'MissingProduct',
        ];

        $this->client->request('PUT', '/api/attributes/666', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Attribute" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Put with a not existing type id does return an error.
     */
    public function testPutInvalidType()
    {
        $data = [
            'name' => 'InvalidType',
            'type' => [
                'id' => 666,
            ],
            'locale' => self::REQUEST_LOCALE,
        ];

        $this->client->request('PUT', '/api/attributes/1', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'Entity with the type "SuluProductBundle:AttributeType" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Put a new attribute name does change the name of the attribute for the given id.
     */
    public function testPutNewName()
    {
        $data = [
            'name' => 'Some new name',
            'locale' => self::REQUEST_LOCALE,
        ];

        $this->client->request('PUT', '/api/attributes/1', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('some-translation-type-1-string', $response->type->name);
        $this->assertEquals('Some new name', $response->name);

        $this->client->request('GET', '/api/attributes/1');
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('some-translation-type-1-string', $response->type->name);
        $this->assertEquals('Some new name', $response->name);
    }

    /**
     * Put a new type does change the type of the attribute for the given id.
     */
    public function testPutNewType()
    {
        $data = [
            'type' => [
                'id' => $this->attributeType2->getId(),
            ],
            'locale' => self::REQUEST_LOCALE,
        ];

        $this->client->request('PUT', '/api/attributes/1', $data);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/attributes/1');
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('some-translation-type-2-string', $response->type->name);
        $this->assertEquals('Gas', $response->name);
    }

    /**
     * Put with an empty type does not change anything.
     */
    public function testPutNewTypeWithoutId()
    {
        $data = [
            'type' => [],
            'locale' => self::REQUEST_LOCALE,
        ];

        $this->client->request('PUT', '/api/attributes/1', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('some-translation-type-1-string', $response->type->name);
        $this->assertEquals('Gas', $response->name);

        $this->client->request('GET', '/api/attributes/1');
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('some-translation-type-1-string', $response->type->name);
        $this->assertEquals('Gas', $response->name);
    }

    /**
     * Delete an existing attribute.
     */
    public function testDeleteById()
    {
        $this->client->request('DELETE', '/api/attributes/1');
        $this->assertEquals('204', $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/attributes/1');
        $this->assertEquals('404', $this->client->getResponse()->getStatusCode());
    }

    /**
     * Delete an not existing attribute.
     */
    public function testDeleteNotExistingById()
    {
        $this->client->request('DELETE', '/api/attributes/666');
        $this->assertEquals('404', $this->client->getResponse()->getStatusCode());
    }

    /**
     * Test if fixtures were correctly created.
     */
    public function testAttributeFixtures()
    {
        $fixtureLoader = new LoadAttributes();
        $fixtureLoader->setContainer($this->getContainer());

        $fixtureLoader->load($this->em);

        $this->client->request('GET', '/api/attributes');
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->attributes;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(3, count($items));

        $item1 = $items[0];
        $this->assertEquals('some-translation-type-1-string', $item1->type->name);
        $this->assertEquals('Gas', $item1->name);

        $item2 = $items[1];
        $this->assertEquals('some-translation-type-2-string', $item2->type->name);
        $this->assertEquals('Power', $item2->name);

        $item3 = $items[2];
        $this->assertEquals('some-translation-type-1-string', $item3->type->name);
        $this->assertEquals('English Attribute', $item3->name);
    }
}
