<?php

namespace Sulu\Bundle\ProductBundle\Tests\Resources;

use DateTime;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Container;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\CategoryBundle\Entity\CategoryTranslation;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\DeliveryStatuses\LoadDeliveryStatuses;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\TaxClasses\LoadTaxClasses;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\Currencies\LoadCurrencies;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\ProductStatuses\LoadProductStatuses;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\ProductTypes\LoadProductTypes;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\Units\LoadUnits;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\CountryTaxes\LoadCountryTaxes;
use Sulu\Bundle\ProductBundle\Entity\Currency;
use Sulu\Bundle\ProductBundle\Entity\CurrencyRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice;
use Sulu\Bundle\ProductBundle\Entity\TaxClass;
use Sulu\Bundle\ProductBundle\Entity\TaxClassRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\StatusRepository;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Entity\TypeRepository;
use Sulu\Bundle\ProductBundle\Entity\Unit;
use Sulu\Bundle\ProductBundle\Entity\UnitRepository;
use Sulu\Bundle\ProductBundle\Product\ProductFactoryInterface;
use Sulu\Bundle\ContactBundle\DataFixtures\ORM\LoadCountries;

class ProductTestData
{
    use TestDataTrait;

    const LOCALE = 'de';

    const CONTENT_UNIT_ID = 2;
    const ORDER_UNIT_ID = 1;
    const PRODUCT_TYPE_ID = 1;

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
     * @var Status
     */
    private $productStatus;

    /**
     * @var Status
     */
    private $productStatusChanged;

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
    private $entityManager;

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
    private $eurCurrency;

    /**
     * @var TaxClass
     */
    private $taxClass;

    /**
     * @param Container $container
     */
    public function __construct(
        Container $container
    ) {
        $this->container = $container;

        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->productFactory = $this->container->get('sulu_product.product_factory');

        $this->createFixtures();
    }

    /**
     * Create fixtures for product test data.
     */
    protected function createFixtures()
    {
        $countries = new LoadCountries();
        $countries->load($this->entityManager);

        $this->contactTestData = new ContactTestData($this->container);

        $loadCurrencies = new LoadCurrencies();
        $loadCurrencies->load($this->entityManager);

        $this->eurCurrency = $this->getCurrencyRepository()->findByCode('EUR');

        $unitFixtures = new LoadUnits();
        $unitFixtures->load($this->entityManager);
        $this->orderUnit = $this->getProductUnitRepository()->find(self::ORDER_UNIT_ID);

        $this->contentUnit = $this->getProductUnitRepository()->find(self::CONTENT_UNIT_ID);

        $taxClasses = new LoadTaxClasses();
        $taxClasses->load($this->entityManager);
        $this->taxClass = $this->getTaxClassRepository()->find(1);

        $countryTaxes = new LoadCountryTaxes();
        $countryTaxes->load($this->entityManager);

        $typeFixtures = new LoadProductTypes();
        $typeFixtures->load($this->entityManager);
        $this->productType = $this->getProductTypeRepository()->find(self::PRODUCT_TYPE_ID);

        $statusFixtures = new LoadProductStatuses();
        $statusFixtures->load($this->entityManager);
        $this->productStatus = $this->getProductStatusRepository()->find(Status::ACTIVE);
        $this->productStatusChanged = $this->getProductStatusRepository()->find(Status::CHANGED);

        $taxFixtures = new LoadTaxClasses();
        $taxFixtures->load($this->entityManager);

        $deliveryStatusFixtures = new LoadDeliveryStatuses();
        $deliveryStatusFixtures->load($this->entityManager);

        $this->product = $this->createProduct();
        $this->product2 = $this->createProduct();

        $this->category = $this->createCategory();
        $this->product->addCategory($this->category);
        $this->product2->addCategory($this->category);
    }

    /**
     * Creates a product.
     *
     * @return ProductInterface
     */
    public function createProduct()
    {
        $this->productCount++;

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
        $price->setCurrency($this->eurCurrency);
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
        $this->categoryCount++;
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
     * @return UnitRepository
     */
    private function getProductUnitRepository()
    {
        return $this->container->get('sulu_product.unit_repository');
    }

    /**
     * @return TaxClassRepository
     */
    private function getTaxClassRepository()
    {
        return $this->container->get('sulu_product.tax_class_repository');
    }

    /**
     * @return StatusRepository
     */
    private function getProductStatusRepository()
    {
        return $this->container->get('sulu_product.status_repository');
    }

    /**
     * @return TypeRepository
     */
    private function getProductTypeRepository()
    {
        return $this->container->get('sulu_product.type_repository');
    }

    /**
     * @return CurrencyRepository
     */
    private function getCurrencyRepository()
    {
        return $this->container->get('sulu_product.currency_repository');
    }
}
