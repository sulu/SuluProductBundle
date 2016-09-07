<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Tests\Resources;

use DateTime;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityRepository;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\CategoryBundle\Entity\CategoryTranslation;
use Sulu\Bundle\ContactBundle\DataFixtures\ORM\LoadCountries;
use Sulu\Bundle\MediaBundle\DataFixtures\ORM\LoadCollectionTypes;
use Sulu\Bundle\MediaBundle\DataFixtures\ORM\LoadMediaTypes;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\AttributeTypes\LoadAttributeTypes;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\CountryTaxes\LoadCountryTaxes;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\Currencies\LoadCurrencies;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\DeliveryStatuses\LoadDeliveryStatuses;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\ProductStatuses\LoadProductStatuses;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\ProductTypes\LoadProductTypes;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\TaxClasses\LoadTaxClasses;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\Units\LoadUnits;
use Sulu\Bundle\ProductBundle\Entity\Addon;
use Sulu\Bundle\ProductBundle\Entity\AddonPrice;
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\AttributeType;
use Sulu\Bundle\ProductBundle\Entity\AttributeTypeRepository;
use Sulu\Bundle\ProductBundle\Entity\AttributeValue;
use Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation;
use Sulu\Bundle\ProductBundle\Entity\Currency;
use Sulu\Bundle\ProductBundle\Entity\CurrencyRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Entity\SpecialPrice;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\StatusRepository;
use Sulu\Bundle\ProductBundle\Entity\TaxClass;
use Sulu\Bundle\ProductBundle\Entity\TaxClassRepository;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Entity\TypeRepository;
use Sulu\Bundle\ProductBundle\Entity\Unit;
use Sulu\Bundle\ProductBundle\Entity\UnitRepository;
use Sulu\Bundle\ProductBundle\Product\ProductFactoryInterface;
use Symfony\Component\DependencyInjection\Container;

class ProductTestData
{
    use TestDataTrait;

    const LOCALE = 'de';

    const MEDIA_TYPE_ID = 1;
    const COLLECTION_TYPE_ID = 1;
    const ATTRIBUTE_TYPE_ID = 1;
    const CONTENT_UNIT_ID = 2;
    const ORDER_UNIT_ID = 1;
    const PRODUCT_TYPE_ID = 1;
    const PRODUCT_TYPE_ADDON_ID = 3;
    const TAX_CLASS_ID = 1;

    /**
     * @var Unit
     */
    private $orderUnit;

    /**
     * @var Unit
     */
    private $contentUnit;

    /**
     * @var Type
     */
    private $productType;

    /**
     * @var Type
     */
    private $addonProductType;

    /**
     * @var Status
     */
    private $productStatus;

    /**
     * @var Status
     */
    private $productStatusChanged;

    /**
     * @var Status
     */
    private $productStatusImported;

    /**
     * @var Status
     */
    private $productStatusSubmitted;

    /**
     * @var ProductInterface
     */
    private $product;

    /**
     * @var ProductInterface
     */
    private $product2;

    /**
     * @var Category
     */
    private $category;

    /**
     * @var ObjectManager
     */
    protected $entityManager;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var ProductFactoryInterface
     */
    private $productFactory;

    /**
     * @var int
     */
    private $productCount = 0;

    /**
     * @var ContactTestData
     */
    private $contactTestData;

    /**
     * @var int
     */
    private $categoryCount = 0;

    /**
     * @var Currency
     */
    private $currency;

    /**
     * @var TaxClass
     */
    private $taxClass;

    /**
     * @var string
     */
    private $defaultCurrencyCode;

    /**
     * @var AttributeType
     */
    private $attributeType;

    /**
     * @param Container $container
     * @param bool $doCreateProducts
     */
    public function __construct(
        Container $container,
        $doCreateProducts = true
    ) {
        $this->container = $container;

        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->productFactory = $this->container->get('sulu_product.product_factory');

        $this->defaultCurrencyCode = $container->getParameter('sulu_product.default_currency');

        $this->createFixtures();

        if ($doCreateProducts) {
            $this->createInitialProducts();
        }
    }

    /**
     * Create two products and add categories.
     * Function is called by constructor by default.
     */
    private function createInitialProducts()
    {
        $this->product = $this->createProduct();
        $this->product2 = $this->createProduct();

        $this->category = $this->createCategory();
        $this->product->addCategory($this->category);
        $this->product2->addCategory($this->category);
    }

    /**
     * Create fixtures for product test data.
     */
    protected function createFixtures()
    {
        // Due to a dubious bug(?) in doctrine - product types need to be loaded first.
        $typeFixtures = new LoadProductTypes();
        $typeFixtures->load($this->entityManager);
        $this->productType = $this->getProductTypeRepository()->find(self::PRODUCT_TYPE_ID);
        $this->addonProductType = $this->getProductTypeRepository()->find(self::PRODUCT_TYPE_ADDON_ID);

        $countries = new LoadCountries();
        $countries->load($this->entityManager);

        $this->contactTestData = new ContactTestData($this->container);

        $loadCurrencies = new LoadCurrencies();
        $loadCurrencies->load($this->entityManager);

        $this->currency = $this->getCurrencyRepository()->findByCode($this->defaultCurrencyCode);

        $unitFixtures = new LoadUnits();
        $unitFixtures->load($this->entityManager);
        $this->orderUnit = $this->getProductUnitRepository()->find(self::ORDER_UNIT_ID);

        $this->contentUnit = $this->getProductUnitRepository()->find(self::CONTENT_UNIT_ID);

        $taxClasses = new LoadTaxClasses();
        $taxClasses->load($this->entityManager);
        $this->taxClass = $this->getTaxClassRepository()->find(self::TAX_CLASS_ID);

        $countryTaxes = new LoadCountryTaxes();
        $countryTaxes->load($this->entityManager);

        $collectionTypes = new LoadCollectionTypes();
        $collectionTypes->load($this->entityManager);

        $mediaTypes = new LoadMediaTypes();
        $mediaTypes->load($this->entityManager);

        $attributeTypes = new LoadAttributeTypes();
        $attributeTypes->load($this->entityManager);
        $this->attributeType = $this->getAttributeTypeRepository()->find(self::ATTRIBUTE_TYPE_ID);

        $statusFixtures = new LoadProductStatuses();
        $statusFixtures->load($this->entityManager);
        $this->productStatus = $this->getProductStatusRepository()->find(Status::ACTIVE);
        $this->productStatusChanged = $this->getProductStatusRepository()->find(Status::CHANGED);
        $this->productStatusImported = $this->getProductStatusRepository()->find(Status::IMPORTED);
        $this->productStatusSubmitted = $this->getProductStatusRepository()->find(Status::SUBMITTED);

        $deliveryStatusFixtures = new LoadDeliveryStatuses();
        $deliveryStatusFixtures->load($this->entityManager);
    }

    /**
     * Creates a product.
     *
     * @return ProductInterface
     */
    public function createProduct()
    {
        ++$this->productCount;

        // Create basic product.
        $product = $this->productFactory->createEntity();
        $this->entityManager->persist($product);
        $product->setNumber('ProductNumber-' . $this->productCount);
        $product->setManufacturer('EnglishManufacturer-' . $this->productCount);
        $product->setType($this->productType);
        $product->setStatus($this->productStatus);
        $product->setCreated(new DateTime());
        $product->setChanged(new DateTime());
        $product->setSupplier($this->contactTestData->accountSupplier);
        $product->setOrderUnit($this->orderUnit);
        $product->setContentUnit($this->contentUnit);
        $product->setOrderContentRatio(2.0);
        $product->setTaxClass($this->taxClass);

        // Add prices
        $this->addPrice($product, 5.99);
        $this->addPrice($product, 3.85, 4.0);

        // Product translation
        $this->addProductTranslation($product);

        return $product;
    }

    /**
     * Creates a product with type addon.
     *
     * @return ProductInterface
     */
    public function createAddonProduct()
    {
        ++$this->productCount;

        // Create basic product.
        $product = $this->productFactory->createEntity();
        $this->entityManager->persist($product);
        $product->setNumber('AddonProductNumber-' . $this->productCount);
        $product->setManufacturer('EnglishManufacturer-' . $this->productCount);
        $product->setType($this->addonProductType);
        $product->setStatus($this->productStatus);
        $product->setCreated(new DateTime());
        $product->setChanged(new DateTime());
        $product->setSupplier($this->contactTestData->accountSupplier);
        $product->setOrderUnit($this->orderUnit);
        $product->setContentUnit($this->contentUnit);
        $product->setOrderContentRatio(2.0);
        $product->setTaxClass($this->taxClass);

        // Add prices
        $this->addPrice($product, 5.99);
        $this->addPrice($product, 3.85, 4.0);

        // Product translation
        $this->addProductTranslation($product);

        return $product;
    }

    /**
     * @param ProductInterface $product
     * @param ProductInterface $addonProduct
     *
     * @return Addon
     */
    public function createAddon(ProductInterface $product, ProductInterface $addonProduct)
    {
        /** @var Addon $addon */
        $addon = $this->entityManager->getRepository('SuluProductBundle:Addon')->createNew();
        $addon->setAddon($addonProduct);
        $addon->setProduct($product);

        $this->entityManager->persist($addon);

        return $addon;
    }

    /**
     * @param Addon $addon
     * @param float $price
     * @param Currency $currency
     *
     * @return AddonPrice
     */
    public function createAddonPrice(Addon $addon, $price, Currency $currency)
    {
        $addonPrice = new AddonPrice();
        $addonPrice->setAddon($addon);
        $addonPrice->setCurrency($currency);
        $addonPrice->setPrice($price);

        $this->entityManager->persist($addonPrice);

        $addon->addAddonPrice($addonPrice);

        return $addonPrice;
    }

    /**
     * Creates a new price entity and adds it to the product.
     *
     * @param ProductInterface $product
     * @param float $priceValue
     * @param null|float $minimumQuantity
     *
     * @return ProductPrice
     */
    public function addPrice($product, $priceValue, $minimumQuantity = null)
    {
        $price = new ProductPrice();
        $this->entityManager->persist($price);

        // Set values.
        $price->setCurrency($this->currency);
        $price->setPrice($priceValue);
        if ($minimumQuantity !== null) {
            $price->setMinimumQuantity($minimumQuantity);
        }

        // Add to product.
        $price->setProduct($product);
        $product->addPrice($price);

        return $price;
    }

    /**
     * Creates a new translation for a given product.
     *
     * @param ProductInterface $product
     * @param string $locale
     */
    public function addProductTranslation($product, $locale = 'en')
    {
        $productTranslation = new ProductTranslation();
        $this->entityManager->persist($productTranslation);

        // Set values.
        $productTranslation->setProduct($product);
        $productTranslation->setLocale($locale);
        $productTranslation->setName('EnglishProductTranslationName-' . $this->productCount);
        $productTranslation->setShortDescription('EnglishProductShortDescription-' . $this->productCount);
        $productTranslation->setLongDescription('EnglishProductLongDescription-' . $this->productCount);

        // Add to product.
        $product->addTranslation($productTranslation);
    }

    /**
     * Create new Category.
     *
     * @return Category
     */
    public function createCategory()
    {
        ++$this->categoryCount;
        $category = new Category();
        $category->setKey('test-category ' . $this->categoryCount);
        $category->setDefaultLocale(self::LOCALE);

        $translation = new CategoryTranslation();
        $translation->setLocale(self::LOCALE);
        $translation->setCategory($category);
        $translation->setTranslation('category-' . $this->categoryCount);
        $category->addTranslation($translation);

        $this->entityManager->persist($category);

        return $category;
    }

    /**
     * Create new Media.
     *
     * @return Media
     */
    public function createMedia()
    {
        $collection = new Collection();
        $this->entityManager->persist($collection);
        $collection->setType($this->getCollectionTypeRepository()->find(1));

        $media = new Media();
        $this->entityManager->persist($media);
        $media->setType($this->getMediaTypeRepository()->find(self::MEDIA_TYPE_ID));
        $media->setCollection($collection);

        return $media;
    }

    /**
     * Creates a product attribute (relation).
     *
     * @param ProductInterface $product
     * @param string $value
     * @param string $locale
     *
     * @return ProductAttribute
     */
    public function createProductAttribute(ProductInterface $product, $value, $locale = 'en')
    {
        $attribute = $this->createAttribute();

        $productAttribute = new ProductAttribute();
        $this->entityManager->persist($productAttribute);
        $productAttribute->setAttribute($attribute);
        $productAttribute->setProduct($product);
        $productAttribute->setAttributeValue($this->createAttributeValue($attribute, $value, $locale));

        return $productAttribute;
    }

    /**
     * Create a product attribute value.
     *
     * @param Attribute $attribute
     * @param string $value
     * @param string $locale
     *
     * @return AttributeValue
     */
    public function createAttributeValue(Attribute $attribute, $value, $locale = 'en')
    {
        $attributeValue = new AttributeValue();
        $this->entityManager->persist($attributeValue);
        $attributeValue->setAttribute($attribute);
        $attributeValue->addTranslation($this->createAttributeValueTranslation($attributeValue, $value, $locale));

        return $attributeValue;
    }

    /**
     * Create a product attribute value translation.
     *
     * @param AttributeValue $attributeValue
     * @param string $value
     * @param string $locale
     *
     * @return AttributeValueTranslation
     */
    public function createAttributeValueTranslation(AttributeValue $attributeValue, $value, $locale = 'en')
    {
        $attributeValueTranslation = new AttributeValueTranslation();
        $this->entityManager->persist($attributeValueTranslation);
        $attributeValueTranslation->setLocale($locale);
        $attributeValueTranslation->setName($value);
        $attributeValueTranslation->setAttributeValue($attributeValue);

        return $attributeValueTranslation;
    }

    /**
     * Creates a new Attribute.
     *
     * @return Attribute
     */
    public function createAttribute()
    {
        $attribute = new Attribute();
        $this->entityManager->persist($attribute);
        $attributeType = $this->getAttributeTypeRepository()->find(self::ATTRIBUTE_TYPE_ID);
        $attribute->setType($attributeType);
        $attribute->setCreated(new DateTime());
        $attribute->setChanged(new DateTime());

        return $attribute;
    }

    /**
     * Creates a special price, which is valid +/- 1 month.
     *
     * @param ProductInterface $product
     * @param float $price
     *
     * @return SpecialPrice
     */
    public function createSpecialPrice(ProductInterface $product, $price)
    {
        $currency = $this->getCurrencyRepository()->find($this->currency->getId());
        $now = new DateTime();

        $specialPrice = new SpecialPrice();
        $this->entityManager->persist($specialPrice);
        $specialPrice->setCurrency($currency);
        $specialPrice->setPrice($price);
        $specialPrice->setProduct($product);
        $specialPrice->setStartDate($now->modify('- 1 month'));
        $specialPrice->setEndDate($now->modify('+ 1 month'));

        return $specialPrice;
    }

    /**
     * @return Unit
     */
    public function getOrderUnit()
    {
        return $this->orderUnit;
    }

    /**
     * @return Type
     */
    public function getProductType()
    {
        return $this->productType;
    }

    /**
     * @return Status
     */
    public function getProductStatus()
    {
        return $this->productStatus;
    }

    /**
     * @return Status
     */
    public function getProductStatusChanged()
    {
        return $this->productStatusChanged;
    }

    /**
     * @return Status
     */
    public function getProductStatusSubmitted()
    {
        return $this->productStatusSubmitted;
    }

    /**
     * @return Status
     */
    public function getProductStatusImported()
    {
        return $this->productStatusImported;
    }

    /**
     * @return ProductInterface
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * @return ProductInterface
     */
    public function getProduct2()
    {
        return $this->product2;
    }

    /**
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @return Unit
     */
    public function getContentUnit()
    {
        return $this->contentUnit;
    }

    /**
     * @return Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return null|TaxClass
     */
    public function getTaxClass()
    {
        return $this->getTaxClassRepository()->find(self::TAX_CLASS_ID);
    }

    /**
     * @return UnitRepository
     */
    protected function getProductUnitRepository()
    {
        return $this->container->get('sulu_product.unit_repository');
    }

    /**
     * @return TaxClassRepository
     */
    protected function getTaxClassRepository()
    {
        return $this->container->get('sulu_product.tax_class_repository');
    }

    /**
     * @return StatusRepository
     */
    protected function getProductStatusRepository()
    {
        return $this->container->get('sulu_product.status_repository');
    }

    /**
     * @return TypeRepository
     */
    protected function getProductTypeRepository()
    {
        return $this->container->get('sulu_product.type_repository');
    }

    /**
     * @return CurrencyRepository
     */
    protected function getCurrencyRepository()
    {
        return $this->container->get('sulu_product.currency_repository');
    }

    /**
     * @return AttributeTypeRepository
     */
    protected function getAttributeTypeRepository()
    {
        return $this->container->get('sulu_product.attribute_type_repository');
    }

    /**
     * @return EntityRepository
     */
    protected function getMediaTypeRepository()
    {
        return $this->entityManager->getRepository('SuluMediaBundle:MediaType');
    }

    /**
     * @return EntityRepository
     */
    protected function getCollectionTypeRepository()
    {
        return $this->entityManager->getRepository('SuluMediaBundle:CollectionType');
    }
}
