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
use Doctrine\ODM\PHPCR\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\AttributeTranslation;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Component\HttpKernel\Client;
use Sulu\Bundle\ProductBundle\Entity\AttributeType;

class AttributeControllerTest extends SuluTestCase
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
     * @var AttributeType
     */
    protected $attributeType2;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var AttributeType
     */
    private $attributeType1;

    /**
     * @var Attribute
     */
    private $attributeEntity1;

    /**
     * @var Attribute
     */
    private $attribute1;

    /**
     * @var Attribute
     */
    private $attributeEntity2;

    /**
     * @var Attribute
     */
    private $attribute2;

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
        $this->attributeType1 = new AttributeType();
        $this->attributeType1->setName('some-translation-type-1-string');
        $this->attributeType2 = new AttributeType();
        $this->attributeType2->setName('some-translation-type-2-string');

        // shipping
        $metadata = $this->em->getClassMetaData(get_class(new Attribute()));
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);

        $this->attribute1 = new Attribute();
        $this->attribute1->setId(Attribute::ATTRIBUTE_TYPE_TEXT);
        $this->attribute1->setCreated(new DateTime());
        $this->attribute1->setChanged(new DateTime());
        $this->attribute1->setType($this->attributeType1);
        $attributeTextTranslation = new AttributeTranslation();
        $attributeTextTranslation->setName('Gas');
        $attributeTextTranslation->setLocale('en');
        $attributeTextTranslation->setAttribute($this->attribute1);
        $this->attribute1->addTranslation($attributeTextTranslation);

        $this->attribute2 = new Attribute();
        $this->attribute2->setCreated(new DateTime());
        $this->attribute2->setChanged(new DateTime());
        $this->attribute2->setType($this->attributeType2);
        $attributeTextTranslation2 = new AttributeTranslation();
        $attributeTextTranslation2->setName('Power');
        $attributeTextTranslation2->setLocale('en');
        $attributeTextTranslation2->setAttribute($this->attribute2);
        $this->attribute2->addTranslation($attributeTextTranslation2);

        $this->em->persist($this->attributeType1);
        $this->em->persist($this->attribute1);
        $this->em->persist($this->attributeType2);
        $this->em->persist($this->attribute2);
    }

    /**
     * Get a existing attribute by it's id
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
     * Get a not existing attribute by it's id
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
     * Get all available attributes
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
     * Get all available attributes flat
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
     * Post (create) a new attribute
     */
    public function testPost()
    {
        $data = array(
            'name' => 'Material',
            'type' => array(
                'id' => $this->attributeType1->getId()
            )
        );

        $this->client->request('POST', '/api/attributes', $data);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals('Material', $response->name);
        $this->assertEquals($this->attributeType1->getId(), $response->type->id);

        $this->client->request('GET', '/api/attributes/' . $response->id);
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('Material', $response->name);
        $this->assertEquals($this->attributeType1->getId(), $response->type->id);
    }

    /**
     * Post with an invalid attribute type does not create a new attribute
     */
    public function testPostInvalidType()
    {
        $data = array(
            'name' => 'InvalidType',
            'type' => array(
                'id' => 666
            )
        );

        $this->client->request('POST', '/api/attributes', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'Entity with the type "SuluProductBundle:AttributeType" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Post with a missing attribute name does not create a new attribute
     */
    public function testPostMissingNameData()
    {
        $data = array(
            'type' => array(
                'id' => 1
            )
        );

        $this->client->request('POST', '/api/attributes', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'The "SuluProductBundle:Attribute"-entity requires a "name"-argument',
            $response->message
        );
    }

    /**
     * Post with a missing type object does not create a new attribute
     */
    public function testPostMissingTypeData()
    {
        $data = array(
            'name' => 'Some name'
        );

        $this->client->request('POST', '/api/attributes', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'The "SuluProductBundle:Attribute"-entity requires a "type"-argument',
            $response->message
        );
    }

    /**
     * Post with a missing type id does not create a new attribute
     */
    public function testPostMissingTypeIdData()
    {
        $data = array(
            'name' => 'InvalidTypeId',
            'type' => array(
            )
        );

        $this->client->request('POST', '/api/attributes', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'The "SuluProductBundle:Attribute"-entity requires a "id"-argument',
            $response->message
        );
    }

    /**
     * Put new name and type to change the appropriate properties on an existing attribute
     */
    public function testPut()
    {
        $data = array(
            'name' => 'Petrol',
            'type' => array(
                'id' => $this->attributeType2->getId()
            )
        );

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
     * Put to a not existing attribute id does return an error
     */
    public function testPutNotExisting()
    {
        $data = array(
            'name' => 'MissingProduct',
        );

        $this->client->request('PUT', '/api/attributes/666', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'Entity with the type "SuluProductBundle:Attribute" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Put with a not existing type id does return an error
     */
    public function testPutInvalidType()
    {
        $data = array(
            'name' => 'InvalidType',
            'type' => array(
                'id' => 666
            )
        );

        $this->client->request('PUT', '/api/attributes/1', $data);

        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());

        $this->assertEquals(
            'Entity with the type "SuluProductBundle:AttributeType" and the id "666" not found.',
            $response->message
        );
    }

    /**
     * Put a new attribute name does change the name of the attribute for the given id
     */
    public function testPutNewName()
    {
        $data = array(
            'name' => 'Some new name',
        );

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
     * Put a new type does change the type of the attribute for the given id
     */
    public function testPutNewType()
    {
        $data = array(
            'type' => array(
                'id' => $this->attributeType2->getId()
            )
        );

        $this->client->request('PUT', '/api/attributes/1', $data);
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/attributes/1');
        $response = json_decode($this->client->getResponse()->getContent());
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('some-translation-type-2-string', $response->type->name);
        $this->assertEquals('Gas', $response->name);
    }

    /**
     * Put with an empty type does not change anything
     */
    public function testPutNewTypeWithoutId()
    {
        $data = array(
            'type' => array(
            )
        );

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
     * Delete an existing attribute
     */
    public function testDeleteById()
    {
        $this->client->request('DELETE', '/api/attributes/1');
        $this->assertEquals('204', $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/api/attributes/1');
        $this->assertEquals('404', $this->client->getResponse()->getStatusCode());
    }

    /**
     * Delete an existing attribute
     */
    public function testDeleteNotExistingById()
    {
        $this->client->request('DELETE', '/api/attributes/666');
        $this->assertEquals('404', $this->client->getResponse()->getStatusCode());
    }
}
