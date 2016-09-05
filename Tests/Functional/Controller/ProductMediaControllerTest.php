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
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionMeta;
use Sulu\Bundle\MediaBundle\Entity\CollectionType;
use Sulu\Bundle\MediaBundle\Entity\File;
use Sulu\Bundle\MediaBundle\Entity\FileVersion;
use Sulu\Bundle\MediaBundle\Entity\FileVersionMeta;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Bundle\MediaBundle\Entity\MediaType;
use Sulu\Bundle\ProductBundle\Entity\AttributeValue;
use Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation;
use Sulu\Bundle\ProductBundle\Entity\Currency;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatus;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatusTranslation;
use Sulu\Bundle\ProductBundle\Entity\Product;
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\AttributeTranslation;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\SpecialPrice;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\StatusTranslation;
use Sulu\Bundle\ProductBundle\Entity\TaxClass;
use Sulu\Bundle\ProductBundle\Entity\TaxClassTranslation;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Entity\AttributeSet;
use Sulu\Bundle\ProductBundle\Entity\AttributeSetTranslation;
use Sulu\Bundle\ProductBundle\Product\ProductRepositoryInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Bundle\ProductBundle\Entity\AttributeType;

class ProductMediaControllerTest extends SuluTestCase
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
     * @var AttributeSetTranslation
     */
    private $attributeSetTranslation1;

    /**
     * @var AttributeTranslation
     */
    private $attributeTranslation1;

    /**
     * @var AttributeValue
     */
    private $attributeValue1;

    /**
     * @var AttributeValueTranslation
     */
    private $attributeValueTranslation1;

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
     * @var AttributeSetTranslation
     */
    private $attributeSetTranslation2;

    /**
     * @var AttributeTranslation
     */
    private $attributeTranslation2;

    /**
     * @var AttributeValue
     */
    private $attributeValue2;

    /**
     * @var AttributeValueTranslation
     */
    private $attributeValueTranslation2;

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

    /**
     * @var MediaType
     */
    private $documentType;

    /**
     * @var Collection
     */
    private $collection;

    /**
     * @var CollectionType
     */
    private $collectionType;

    /**
     * @var CollectionMeta
     */
    private $collectionMeta;

    /**
     * @var Media
     */
    private $media1;
    /**
     * @var Media
     */
    private $media2;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->em = $this->getEntityManager();
        $this->purgeDatabase();

        $this->setUpCollection();
        $this->setUpMedia();
        $this->setUpTestData();

        $this->client = $this->createAuthenticatedClient();
        $this->em->flush();
    }

    /**
     * Set up product data.
     */
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
        $this->type1->setTranslationKey('Type1');

        // product status
        $metadata = $this->em->getClassMetadata(Status::class);
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

        $metadata = $this->em->getClassMetadata(Attribute::class);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);

        $this->attribute1 = new Attribute();
        $this->attribute1->setId(Attribute::ATTRIBUTE_TYPE_TEXT);
        $this->attribute1->setCreated(new DateTime());
        $this->attribute1->setChanged(new DateTime());
        $this->attribute1->setType($this->attributeType1);
        $this->attribute1->setKey('key-1');

        // Attribute Translations
        $this->attributeTranslation1 = new AttributeTranslation();
        $this->attributeTranslation1->setAttribute($this->attribute1);
        $this->attributeTranslation1->setLocale('en');
        $this->attributeTranslation1->setName('EnglishAttribute-1');

        // Attribute Value
        $this->attributeValue1 = new AttributeValue();
        $this->attributeValue1->setAttribute($this->attribute1);

        // Attribute Value Translation
        $this->attributeValueTranslation1 = new AttributeValueTranslation();
        $this->attributeValueTranslation1->setLocale('en');
        $this->attributeValueTranslation1->setName('EnglishAttributeValue-1');
        $this->attributeValueTranslation1->setAttributeValue($this->attributeValue1);

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
        $this->productAttribute1->setProduct($this->product1);
        $this->productAttribute1->setAttribute($this->attribute1);
        $this->productAttribute1->setAttributeValue($this->attributeValue1);

        // Product 2
        // product type
        $this->type2 = new Type();
        $this->type2->setTranslationKey('Type2');

        // product status
        $metadata = $this->em->getClassMetadata(Status::class);
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
        $this->attribute2->setKey('key-2');

        // Attribute Translations
        $this->attributeTranslation2 = new AttributeTranslation();
        $this->attributeTranslation2->setAttribute($this->attribute2);
        $this->attributeTranslation2->setLocale('en');
        $this->attributeTranslation2->setName('EnglishAttribute-2');

        // Attribute Value
        $this->attributeValue2 = new AttributeValue();
        $this->attributeValue2->setAttribute($this->attribute2);

        // Attribute Value Translation
        $this->attributeValueTranslation2 = new AttributeValueTranslation();
        $this->attributeValueTranslation2->setLocale('en');
        $this->attributeValueTranslation2->setName('EnglishAttributeValue-2');
        $this->attributeValueTranslation2->setAttributeValue($this->attributeValue2);

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
        $this->productAttribute2->setProduct($this->product2);
        $this->productAttribute2->setAttribute($this->attribute2);
        $this->productAttribute2->setAttributeValue($this->attributeValue2);

        $metadata = $this->em->getClassMetadata(TaxClass::class);
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
        $this->category1->setDefaultLocale('en');
        $categoryTranslation1 = new CategoryTranslation();
        $categoryTranslation1->setLocale('en');
        $categoryTranslation1->setTranslation('Category 1');
        $categoryTranslation1->setCategory($this->category1);
        $this->category1->addTranslation($categoryTranslation1);

        $this->category2 = new Category();
        $this->category2->setLft(3);
        $this->category2->setRgt(4);
        $this->category2->setDepth(1);
        $this->category2->setDefaultLocale('en');
        $categoryTranslation2 = new CategoryTranslation();
        $categoryTranslation2->setLocale('en');
        $categoryTranslation2->setTranslation('Category 2');
        $categoryTranslation2->setCategory($this->category2);
        $this->category2->addTranslation($categoryTranslation2);

        $metadata = $this->em->getClassMetadata(DeliveryStatus::class);
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
        $this->specialPrice1->setStartDate(new \DateTime());
        $this->specialPrice1->setEndDate(new \DateTime());

        // add media to product
        $this->media1 = $this->createMedia('test');
        $this->product1->addMedia($this->media1);
        $this->media2 = $this->createMedia('test2');
        $this->product1->addMedia($this->media2);

        $this->product1->addCategory($this->category1);
        $this->product1->addCategory($this->category2);

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
        $this->em->persist($this->attributeSet1);
        $this->em->persist($this->attributeSetTranslation1);
        $this->em->persist($this->productStatus1);
        $this->em->persist($this->productStatusTranslation1);
        $this->em->persist($this->attribute1);
        $this->em->persist($this->attributeTranslation1);
        $this->em->persist($this->attributeValue1);
        $this->em->persist($this->attributeValueTranslation1);
        $this->em->persist($this->product1);
        $this->em->persist($productTranslation1);
        $this->em->persist($this->productAttribute1);

        $this->em->persist($this->type2);
        $this->em->persist($this->attributeType2);
        $this->em->persist($this->attributeSet2);
        $this->em->persist($this->attributeSetTranslation2);
        $this->em->persist($this->productStatus2);
        $this->em->persist($this->productStatusTranslation2);
        $this->em->persist($this->attribute2);
        $this->em->persist($this->attributeTranslation2);
        $this->em->persist($this->attributeValue2);
        $this->em->persist($this->attributeValueTranslation2);
        $this->em->persist($this->product2);
        $this->em->persist($productTranslation2);
        $this->em->persist($this->productAttribute2);
        $this->em->persist($this->specialPrice1);
        $this->em->flush();
    }

    /**
     * Setup data needed to create media.
     */
    protected function setUpMedia()
    {
        // Create Media Type
        $this->documentType = new MediaType();
        $this->documentType->setName('document');
        $this->documentType->setDescription('This is a document');

        $this->em->persist($this->documentType);

        $this->em->flush();
    }

    /**
     * Create a media entity.
     *
     * @param $name
     *
     * @return Media
     */
    protected function createMedia($name)
    {
        $media = new Media();
        $media->setType($this->documentType);

        // create file
        $file = new File();
        $file->setVersion(1);
        $file->setMedia($media);

        // create file version
        $fileVersion = new FileVersion();
        $fileVersion->setVersion(1);
        $fileVersion->setName($name . '.jpeg');
        $fileVersion->setMimeType('image/jpg');
        $fileVersion->setFile($file);
        $fileVersion->setSize(1124214);
        $fileVersion->setDownloadCounter(2);
        $fileVersion->setChanged(new \DateTime('1937-04-20'));
        $fileVersion->setCreated(new \DateTime('1937-04-20'));
        $fileVersion->setStorageOptions('{"segment":"1","fileName":"' . $name . '.jpeg"}');

        // create meta
        $fileVersionMeta = new FileVersionMeta();
        $fileVersionMeta->setLocale('en-gb');
        $fileVersionMeta->setTitle('media-title');
        $fileVersionMeta->setDescription('media-description');
        $fileVersionMeta->setFileVersion($fileVersion);

        $fileVersion->addMeta($fileVersionMeta);
        $fileVersion->setDefaultMeta($fileVersionMeta);

        $file->addFileVersion($fileVersion);

        $media->addFile($file);
        $media->setCollection($this->collection);

        $this->em->persist($media);
        $this->em->persist($file);
        $this->em->persist($fileVersionMeta);
        $this->em->persist($fileVersion);

        $this->em->flush();

        return $media;
    }

    /**
     * Setup a collection.
     */
    protected function setUpCollection()
    {
        $this->collection = new Collection();
        $style = [
            'type' => 'circle',
            'color' => '#ffcc00',
        ];

        $this->collection->setStyle(json_encode($style));

        // Create Collection Type
        $this->collectionType = new CollectionType();
        $this->collectionType->setName('Default Collection Type');
        $this->collectionType->setDescription('Default Collection Type');

        $this->collection->setType($this->collectionType);

        // Collection Meta 1
        $this->collectionMeta = new CollectionMeta();
        $this->collectionMeta->setTitle('Test Collection');
        $this->collectionMeta->setDescription('This Description is only for testing');
        $this->collectionMeta->setLocale('en-gb');
        $this->collectionMeta->setCollection($this->collection);

        $this->collection->addMeta($this->collectionMeta);

        $this->em->persist($this->collection);
        $this->em->persist($this->collectionType);
        $this->em->persist($this->collectionMeta);
    }

    /**
     * Tests if all media of a product are returned.
     */
    public function testGetAllMedia()
    {
        $this->client->request('GET', '/api/products/' . $this->product1->getId() . '/media?flat=true');
        $response = json_decode($this->client->getResponse()->getContent());
        $items = $response->_embedded->media;

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(2, count($items));

        $this->checkProductAttributes();
    }

    /**
     * Test adding a media to a product.
     */
    public function testPostMedia()
    {
        // add new media
        $this->setUpCollection();
        $this->setUpMedia();
        $media = $this->createMedia('test-post-media');
        $this->em->flush();

        $this->client->request(
            'POST',
            '/api/products/' . $this->product1->getId() . '/media',
            ['mediaId' => $media->getId()]
        );

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $product = $this->checkProductAttributes();

        $this->assertEquals(2, $product->getMedia()->count());
    }

    /**
     * Tests if all media of a product are returned.
     */
    public function testDeleteMedia()
    {
        $this->client->request(
            'DELETE',
            '/api/products/' . $this->product1->getId() . '/media/' . $this->media1->getId()
        );
        $this->assertEquals(204, $this->client->getResponse()->getStatusCode());

        $this->checkProductAttributes();
    }

    /**
     * Function checks if all assigned values are still assigned on a product after
     * performing media operations.
     */
    protected function checkProductAttributes()
    {
        /** @var ProductInterface $product */
        $product = $this->getProductRepository()->find($this->product1->getId());

        $this->assertEquals(2, $product->getPrices()->count());

        $this->assertEquals(2, $product->getCategories()->count());

        return $product;
    }

    /**
     * @return ProductRepositoryInterface
     */
    protected function getProductRepository()
    {
        return $this->getContainer()->get('sulu_product.product_repository');
    }
}
