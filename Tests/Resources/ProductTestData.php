<?php

namespace Sulu\Bundle\ProductBundle\Tests\Resources;

use DateTime;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Container;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\Currencies\LoadCurrencies;
use Sulu\Bundle\ProductBundle\Entity\Currency;
use Sulu\Bundle\ProductBundle\Entity\CurrencyRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\ProductStatuses\LoadProductStatuses;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\ProductTypes\LoadProductTypes;
use Sulu\Bundle\ProductBundle\DataFixtures\ORM\Units\LoadUnits;
use Sulu\Bundle\ProductBundle\Entity\StatusRepository;
use Sulu\Bundle\ProductBundle\Entity\TypeRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\Status;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Entity\Unit;
use Sulu\Bundle\ProductBundle\Product\ProductFactoryInterface;

class ProductTestData
{
    use TestDataTrait;

    /**
     * @var Unit
     */
    public $orderUnit;

    /**
     * @var Type
     */
    public $productType;

    /**
     * @var Status
     */
    public $productStatus;

    /**
     * @var Status
     */
    public $productStatusChanged;

    /**
     * @var ProductInterface
     */
    public $product;

    /**
     * @var ProductInterface
     */
    public $product2;

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
     * @var Category
     */
    private $category;

    /**
     * @var int
     */
    private $categoryCount = 0;

    /**
     * @var Currency
     */
    private $eurCurrency;

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
        $this->contactTestData = new ContactTestData($this->container);

        $loadCurrencies = new LoadCurrencies();
        $loadCurrencies->load($this->entityManager);

        $this->eurCurrency = $this->getCurrencyRepository()->findByCode('EUR');

        $unitFixtures =  new LoadUnits();
        $unitFixtures->load($this->entityManager);
        $this->orderUnit = $this->getProductUnitRepository()->find(1);

        $typeFixtures =  new LoadProductTypes();
        $typeFixtures->load($this->entityManager);
        $this->productType = $this->getProductTypeRepository()->find(1);

        $statusFixtures =  new LoadProductStatuses();
        $statusFixtures->load($this->entityManager);
        $this->productStatus = $this->getProductStatusRepository()->find(Status::ACTIVE);
        $this->productStatusChanged = $this->getProductStatusRepository()->find(Status::CHANGED);

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

        $product = $this->productFactory->createEntity();
        $product->setNumber('ProductNumber-' . $this->productCount);
        $product->setManufacturer('EnglishManufacturer-' . $this->productCount);
        $product->setType($this->productType);
        $product->setStatus($this->productStatus);
        $product->setCreated(new DateTime());
        $product->setChanged(new DateTime());
        $product->setOrderUnit($this->orderUnit);
        $product->setSupplier($this->contactTestData->accountSupplier);

        $price = new ProductPrice();
        $price->setCurrency($this->eurCurrency);
        $price->setPrice(5.99);
        $price->setProduct($product);

        $product->addPrice($price);

        // product translation
        $productTranslation = new ProductTranslation();
        $productTranslation->setProduct($product);
        $productTranslation->setLocale('en');
        $productTranslation->setName('EnglishProductTranslationName-' . $this->productCount);
        $productTranslation->setShortDescription('EnglishProductShortDescription-' . $this->productCount);
        $productTranslation->setLongDescription('EnglishProductLongDescription-' . $this->productCount);
        $product->addTranslation($productTranslation);

        $this->entityManager->persist($price);
        $this->entityManager->persist($product);
        $this->entityManager->persist($productTranslation);

        return $product;
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
        $category->setDefaultLocale('de');

        $this->entityManager->persist($category);

        return $category;
    }

    /**
     * @return StatusRepository
     */
    private function getProductUnitRepository()
    {
       return $this->container->get('sulu_product.unit_repository');
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
