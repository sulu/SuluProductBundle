<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product\Mapper;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryRepository;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\ContactBundle\Entity\AccountRepository;
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatus;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatusRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice;
use Sulu\Bundle\ProductBundle\Entity\TaxClass;
use Sulu\Bundle\ProductBundle\Entity\TaxClassRepository;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Entity\TypeRepository;
use Sulu\Bundle\ProductBundle\Entity\Unit;
use Sulu\Bundle\ProductBundle\Entity\UnitRepository;
use Sulu\Bundle\ProductBundle\Product\AttributeRepositoryInterface;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductDependencyNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductMappingException;
use Sulu\Bundle\ProductBundle\Product\ProductAttributeManager;
use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Bundle\ProductBundle\Product\ProductPriceManagerInterface;
use Sulu\Bundle\ProductBundle\Product\ProductRepositoryInterface;
use Sulu\Bundle\ProductBundle\Traits\ArrayDataTrait;
use Sulu\Bundle\TagBundle\Tag\TagRepositoryInterface;
use Sulu\Component\Persistence\RelationTrait;

/**
 * This service is responsible for mapping data to product.
 */
class ProductMapper implements ProductMapperInterface
{
    use ArrayDataTrait;
    use RelationTrait;

    const MAX_SEARCH_TERMS_LENGTH = 500;

    /**
     * @var ProductManagerInterface
     */
    private $productManager;

    /**
     * @var ProductAttributeManager
     */
    private $productAttributeManager;

    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var TagRepositoryInterface
     */
    private $tagRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var DeliveryStatusRepository
     */
    private $deliveryStatusRepository;

    /**
     * @var TypeRepository
     */
    private $productTypeRepository;

    /**
     * @var UnitRepository
     */
    private $unitRepository;

    /**
     * @var AccountRepository
     */
    private $accountRepository;

    /**
     * @var TaxClassRepository
     */
    private $taxClassRepository;

    /**
     * @var ProductPriceManagerInterface
     */
    private $productPriceManager;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var CategoryRepository
     */
    private $categoryRepository;

    /**
     * @param EntityManagerInterface $entityManager
     * @param ProductManagerInterface $productManager
     * @param ProductRepositoryInterface $productRepository
     * @param ProductAttributeManager $productAttributeManager
     * @param AttributeRepositoryInterface $attributeRepository
     * @param TagRepositoryInterface $tagRepository
     * @param DeliveryStatusRepository $deliveryStatusRepository
     * @param TypeRepository $productTypeRepository
     * @param UnitRepository $unitRepository
     * @param AccountRepository $accountRepository
     * @param TaxClassRepository $taxClassRepository
     * @param ProductPriceManagerInterface $productPriceManager
     * @param CategoryRepository $categoryRepository
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ProductManagerInterface $productManager,
        ProductRepositoryInterface $productRepository,
        ProductAttributeManager $productAttributeManager,
        AttributeRepositoryInterface $attributeRepository,
        TagRepositoryInterface $tagRepository,
        DeliveryStatusRepository $deliveryStatusRepository,
        TypeRepository $productTypeRepository,
        UnitRepository $unitRepository,
        AccountRepository $accountRepository,
        TaxClassRepository $taxClassRepository,
        ProductPriceManagerInterface $productPriceManager,
        CategoryRepository $categoryRepository
    ) {
        $this->entityManager = $entityManager;
        $this->productManager = $productManager;
        $this->productRepository = $productRepository;
        $this->productAttributeManager = $productAttributeManager;
        $this->attributeRepository = $attributeRepository;
        $this->tagRepository = $tagRepository;
        $this->deliveryStatusRepository = $deliveryStatusRepository;
        $this->productTypeRepository = $productTypeRepository;
        $this->unitRepository = $unitRepository;
        $this->accountRepository = $accountRepository;
        $this->taxClassRepository = $taxClassRepository;
        $this->productPriceManager = $productPriceManager;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function map(ProductInterface $product, array $data, $locale)
    {
        $product->setNumber($this->getProperty($data, 'number', $product->getNumber()));
        $product->setPriceInfo($this->getProperty($data, 'priceInfo', $product->getPriceInfo()));
        $product->setGlobalTradeItemNumber(
            $this->getProperty(
                $data,
                'globalTradeItemNumber',
                $product->getGlobalTradeItemNumber()
            )
        );
        $product->setManufacturer($this->getProperty($data, 'manufacturer', $product->getManufacturer()));
        $product->setAreGrossPrices($this->getProperty($data, 'areGrossPrices', $product->getAreGrossPrices()));
        $product->setIsRecurringPrice($this->getProperty($data, 'isRecurringPrice', $product->isRecurringPrice()));

        $this->processSearchTerms($data, $product);

        // Set float values.
        $this->setFloatValueOfDataArray([$product, 'setMinimumOrderQuantity'], $data, 'minimumOrderQuantity');
        $this->setFloatValueOfDataArray([$product, 'setRecommendedOrderQuantity'], $data, 'recommendedOrderQuantity');
        $this->setFloatValueOfDataArray([$product, 'setOrderContentRatio'], $data, 'orderContentRatio', 1);
        $this->setFloatValueOfDataArray([$product, 'setCost'], $data, 'cost');
        $this->setFloatValueOfDataArray([$product, 'setDeliveryTime'], $data, 'deliveryTime', 0);

        // Set translation data.
        $translation = $this->productManager->retrieveOrCreateProductTranslationByLocale($product, $locale);
        $translation->setName($this->getProperty($data, 'name', $translation->getName()));
        $translation->setShortDescription(
            $this->getProperty($data, 'shortDescription', $translation->getShortDescription())
        );
        $translation->setLongDescription(
            $this->getProperty($data, 'longDescription', $translation->getLongDescription())
        );

        // Process relational data.
        $this->processParent($data, $product);
        $this->processAttributes($data, $product, $locale);
        $this->processTags($data, $product);
        $this->processSpecialPrices($data, $product);
        $this->processDeliveryStatus($data, $product);

        $this->processStatus($data, $product);
        $this->processType($data, $product);

        $this->processSupplier($data, $product);
        $this->processTaxClass($data, $product);
        $this->processCategories($data, $product);
        $this->processPrices($data, $product);

        $this->processUnit($data, 'orderUnit', $product);
        $this->processUnit($data, 'contentUnit', $product);

        // TODO: Move to manager: internal-item behaviour.
//        if (!$product->getInternalItemNumber()) {
//            if ($supplierId) {
//                $product->setInternalItemNumber(
//                    $this->generateInternalItemNumber(
//                        self::SUPPLIER_PREFIX,
//                        $supplierId,
//                        $product->getNumber()
//                    )
//                );
//            } else {
//                $product->setInternalItemNumber(
//                    $this->generateInternalItemNumber(
//                        self::USER_PREFIX,
//                        $userId,
//                        $product->getNumber()
//                    )
//                );
//            }
//        }
        // TODO: PUT?, PATCH?, CONFUSION!
        // supplier
        // content-unit

        // TODO: user
//        $user = $this->userRepository->findUserById($userId);

        // TODO: when product is not new.
//            $product->setChanged(new \DateTime());
//            $product->setChanger($user);

        // TODO: if product is new.
//        if ($product->getId() == null) {
//            $product->setCreated(new \DateTime());
//            $product->setCreator($user);
//        }

        // TODO: Existing product: this logic should be part of the manager and not the mapper.
//        $publishedProduct = $this->getExistingActiveOrInactiveProduct($product, $data['status']['id'], $locale);

        // TODO: move to manager
//        if ($publishedProduct) {
//            // Since there is already a published product with the same internal id we are going to update the
//            // existing one with the properties of the current product.
//            $this->convertProduct($product, $publishedProduct);
//
//            $product = $publishedProduct;
//        }

        // TODO: check status.
//        if ($product->getStatus()->getId() == StatusEntity::ACTIVE) {
//            // If the status of the product is active then the product must be a valid shop product!
//            if (!$product->isValidShopProduct($this->defaultCurrency)) {
//                // Undo changes
//                $this->entityManager->refresh($product);
//                throw new ProductException('No valid product for shop!', ProductException::PRODUCT_NOT_VALID);
//            }
//        }

        // TODO: move to manager
        // Set defaults to product.
        $this->processMandatoryFields($product);

        return $product;
    }

    /**
     * {@inheritdoc}
     */
    public function get(ProductInterface $product, $locale)
    {
    }

    /**
     * TODO: Move to manager.
     *
     * Processes product and sets default values if not set already.
     *
     * @param ProductInterface $product
     */
    protected function processMandatoryFields(ProductInterface $product)
    {
        // Default delivery status.
        if (null === $product->getDeliveryStatus()) {
            $deliveryStatus = $this->deliveryStatusRepository->find(DeliveryStatus::AVAILABLE);
            $product->setDeliveryStatus($deliveryStatus);
        }
        // Default Order Unit.
        if (null === $product->getOrderUnit()) {
            $orderUnit = $this->unitRepository->find(Unit::PIECE_ID);
            $product->setOrderUnit($orderUnit);
        }
        if (null === $product->getTaxClass()) {
            // Default tax class
            $taxClass = $this->taxClassRepository->find(TaxClass::STANDARD_TAX_RATE);
            $product->setTaxClass($taxClass);
        }
    }

    /**
     * Processes status of data array.
     *
     * @param array $data
     * @param ProductInterface $product
     *
     * @throws ProductMappingException
     */
    protected function processStatus(array $data, ProductInterface $product)
    {
        if (array_key_exists('status', $data)) {
            // Status id must be given.
            if (!isset($data['status']['id'])) {
                throw new ProductMappingException('status', 'Id is missing.');
            }

            $statusId = $data['status']['id'];
            $this->productManager->setStatusForProduct($product, $statusId);
        }
    }

    /**
     * Processes search terms of data array.
     *
     * @param array $data
     * @param ProductInterface $product
     */
    protected function processSearchTerms(array $data, ProductInterface $product)
    {
        if (array_key_exists('searchTerms', $data)) {
            $searchTerms = $this->parseCommaSeparatedString($data['searchTerms']);
            $product->setSearchTerms($searchTerms);
        }
    }

    /**
     * Processes type of data array.
     *
     * @param array $data
     * @param ProductInterface $product
     *
     * @throws ProductDependencyNotFoundException
     * @throws ProductMappingException
     */
    protected function processType(array $data, ProductInterface $product)
    {
        if (array_key_exists('type', $data)) {
            if (!isset($data['type']['id'])) {
                throw new ProductMappingException('type', 'Id is missing');
            }

            $typeId = $data['type']['id'];
            /** @var Type $type */
            $type = $this->productTypeRepository->find($typeId);
            if (!$type) {
                throw new ProductDependencyNotFoundException($this->productTypeRepository->getClassName(), $typeId);
            }

            $product->setType($type);
        }
    }

    /**
     * Checks if unit key is set in data and sets specific unit.
     *
     * @param array $data
     * @param $key
     * @param ProductInterface $product
     *
     * @throws ProductDependencyNotFoundException
     */
    protected function processUnit(array $data, $key, ProductInterface $product)
    {
        if (array_key_exists($key, $data)) {

            $unit = null;
            if (array_key_exists('id', $data[$key])) {
                $unitId = $data[$key]['id'];
                /** @var Unit $unit */
                $unit = $this->unitRepository->find($unitId);
                if (!$unit) {
                    throw new ProductDependencyNotFoundException($this->unitRepository->getClassName(), $unitId);
                }
            }

            call_user_func($this->retrieveProductSetter($key, $product), $unit);
        }
    }

    /**
     * Sets delivery status to product.
     * If product has no delivery status at all a default is set.
     *
     * @param array $data
     * @param ProductInterface $product
     *
     * @throws ProductDependencyNotFoundException
     */
    protected function processDeliveryStatus(array $data, ProductInterface $product)
    {
        if (array_key_exists('deliveryStatus', $data)) {
            if (isset($data['deliveryStatus']['id'])) {
                $deliveryStatusId = $data['deliveryStatus']['id'];
                /** @var DeliveryStatus $deliveryStatus */
                $deliveryStatus = $this->deliveryStatusRepository->find($deliveryStatusId);
                if (!$deliveryStatus) {
                    throw new ProductDependencyNotFoundException(
                        $this->deliveryStatusRepository->getClassName(),
                        $deliveryStatusId
                    );
                }
                $product->setDeliveryStatus($deliveryStatus);
            }
        }
    }

    /**
     * Processes parent attribute of data array.
     *
     * @param array $data
     * @param ProductInterface $product
     *
     * @throws ProductDependencyNotFoundException
     */
    protected function processParent(array $data, ProductInterface $product)
    {
        if (array_key_exists('parent', $data)) {
            $parentProduct = null;
            if (isset($data['parent']['id'])) {
                $parentId = $data['parent']['id'];
                $parentProduct = $this->productRepository->find($parentId);
                if (!$parentProduct) {
                    throw new ProductDependencyNotFoundException($this->productRepository->getClassName(), $parentId);
                }
            }

            $product->setParent($parentProduct);
        }
    }

    /**
     * Processes parent attribute of data array.
     *
     * @param array $data
     * @param ProductInterface $product
     *
     * @throws ProductDependencyNotFoundException
     */
    protected function processTaxClass(array $data, ProductInterface $product)
    {
        if (array_key_exists('taxClass', $data)) {
            $taxClass = null;
            if (isset($data['taxClass']['id'])) {
                $taxClassId = $data['taxClass']['id'];
                /** @var TaxClass $taxClass */
                $taxClass = $this->taxClassRepository->find($taxClassId);
                if (!$taxClass) {
                    throw new ProductDependencyNotFoundException(
                        $this->taxClassRepository->getClassName(),
                        $taxClassId
                    );
                }
            }

            $product->setTaxClass($taxClass);
        }
    }

    /**
     * Processes attributes of data array.
     *
     * @param array $data
     * @param ProductInterface $product
     * @param string $locale
     *
     * @throws ProductDependencyNotFoundException
     */
    protected function processAttributes(array $data, ProductInterface $product, $locale)
    {
        if (isset($data['attributes'])) {
            // Create local array of all currently assigned attributes of product.
            $productAttributes = [];
            foreach ($product->getProductAttributes() as $productAttribute) {
                $productAttributes[$productAttribute->getAttribute()->getId()] = $productAttribute;
            }

            // Save all attribute ids which are given in the request.
            $attributeIdsInRequest = [];

            // Add and change attributes.
            foreach ($data['attributes'] as $attributeData) {
                if (!isset($attributeData['attributeId'])) {
                    continue;
                }

                $attributeId = $attributeData['attributeId'];
                $attributeIdsInRequest[$attributeId] = $attributeId;

                // Check if a attributeValueName is provided, otherwise do nothing.
                if (!array_key_exists('attributeValueName', $attributeData)) {
                    continue;
                }
                $attributeDataValueName = trim($attributeData['attributeValueName']);

                // Get attributeValueLocale from request.
                $attributeValueLocale = $locale;
                if (isset($attributeData['attributeValueLocale'])) {
                    $attributeValueLocale = $attributeData['attributeValueLocale'];
                }

                // If attribute value is empty do not add.
                if (!$attributeDataValueName) {
                    // If already set on product, remove attribute value translation.
                    if (array_key_exists($attributeId, $productAttributes)) {
                        /** @var ProductAttribute $productAttribute */
                        $productAttribute = $productAttributes[$attributeId];

                        // Remove attribute value translation from attribute value.
                        $this->productAttributeManager->removeAttributeValueTranslation(
                            $productAttribute->getAttributeValue(),
                            $attributeValueLocale
                        );

                        // If no more attribute value translation exists,
                        // remove the whole product attribute from the product.
                        if ($productAttribute->getAttributeValue()->getTranslations()->isEmpty()) {
                            $product->removeProductAttribute($productAttribute);
                            // TODO:
                            $this->entityManager->remove($productAttribute);
                        }
                    }
                    continue;
                }

                // Product attribute does not exists.
                if (!array_key_exists($attributeId, $productAttributes)) {
                    $attribute = $this->retrieveAttributeById($attributeId);
                    // Create new attribute value with translation.
                    $attributeValue = $this->productAttributeManager->createAttributeValue(
                        $attribute,
                        $attributeDataValueName,
                        $attributeValueLocale
                    );
                    // Create new product attribute.
                    $this->productAttributeManager->createProductAttribute($attribute, $product, $attributeValue);
                } else {
                    // Product attribute exists.
                    /** @var ProductAttribute $productAttribute */
                    $productAttribute = $productAttributes[$attributeId];
                    $attributeValue = $productAttribute->getAttributeValue();
                    // Create translation in current locale.
                    $this->productAttributeManager->setOrCreateAttributeValueTranslation(
                        $attributeValue,
                        $attributeDataValueName,
                        $attributeValueLocale
                    );
                }
            }

            // Delete attributes.
            foreach ($productAttributes as $productAttribute) {
                if (!array_key_exists($productAttribute->getAttribute()->getId(), $attributeIdsInRequest)) {
                    // Remove all attribute value translations from attribute value.
                    $this->productAttributeManager->removeAllAttributeValueTranslations(
                        $productAttribute->getAttributeValue()
                    );

                    // Remove attribute from product.
                    $product->removeProductAttribute($productAttribute);
                    $this->entityManager->remove($productAttribute);
                }
            }
        }
    }

    /**
     * Finds an attribute with the given id. Else throws an Exception.
     *
     * @param int $attributeId
     *
     * @throws ProductDependencyNotFoundException
     *
     * @return Attribute
     */
    private function retrieveAttributeById($attributeId)
    {
        /** @var Attribute $attribute */
        $attribute = $this->attributeRepository->find($attributeId);
        if (!$attribute) {
            throw new ProductDependencyNotFoundException($this->attributeRepository->getClassName(), $attributeId);
        }

        return $attribute;
    }

    /**
     * @param array $data
     * @param ProductInterface $product
     */
    protected function processTags(array $data, ProductInterface $product)
    {
        if (array_key_exists('tags', $data)) {
            $product->getTags()->clear();
            if (is_array($data['tags'])) {
                foreach ($data['tags'] as $strTag) {
                    $tag = $this->tagRepository->findTagByName($strTag);
                    if ($tag) {
                        $product->addTag($tag);
                    }
                }
            }
        }
    }

    /**
     * Process special prices of data array.
     *
     * @param array $data
     * @param ProductInterface $product
     */
    protected function processSpecialPrices(array $data, ProductInterface $product)
    {
//        TODO:
//        if (array_key_exists('specialPrices', $data)) {
//            $specialPricesData = $data['specialPrices'];
//
//            // Array for local special prices storage.
//            $specialPrices = [];
//
//            // Array of currency codes to be used as keys for special prices.
//            $currencyCodes = [];
//
//            // Create array of special price currency codes in json request.
//            foreach ($specialPricesData as $key => $specialPrice) {
//                if (!empty($specialPrice['currency']['code']) && !empty($specialPrice['price'])) {
//                    array_push($currencyCodes, $specialPrice['currency']['code']);
//                } else {
//                    unset($specialPricesData[$key]);
//                }
//            }
//
//            // Iterate through already added special prices for this specific product.
//            foreach ($product->getSpecialPrices() as $specialPrice) {
//                // Save special prices to array.
//                $specialPrices[$specialPrice->getCurrency()->getCode()] = $specialPrice;
//
//                // Check if special price code already exists in array if not remove it from product.
//                if (!in_array($specialPrice->getCurrency()->getCode(), $currencyCodes)) {
//                    $product->removeSpecialPrice($specialPrice);
//                    $this->entityManager->remove($specialPrice);
//                }
//            }
//
//            // Iterate through send json array of special prices.
//            foreach ($specialPricesData as $specialPriceData) {
//                // If key does not exists add a new special price to product.
//                if (!array_key_exists($specialPriceData['currency']['code'], $specialPrices)) {
//                    $specialPrice = new SpecialPrice();
//
//                    $currency = $this->currencyRepository->findByCode($specialPriceData['currency']['code']);
//                    $specialPrice->setCurrency($currency);
//
//                    $specialPrice->setProduct($product);
//
//                    $product->addSpecialPrice($specialPrice);
//                } else {
//                    // Else update the already existing special price.
//                    $specialPrice = $specialPrices[$specialPriceData['currency']['code']];
//                }
//
//                if (isset($specialPriceData['price'])) {
//                    $specialPrice->setPrice($specialPriceData['price']);
//                }
//
//                if (isset($specialPriceData['startDate'])) {
//                    $startDate = $this->checkDateString($specialPriceData['startDate']);
//                    $specialPrice->setStartDate($startDate);
//                }
//
//                if (isset($specialPriceData['endDate'])) {
//                    $endDate = $this->checkDateString($specialPriceData['endDate']);
//
//                    if ($endDate) {
//                        // Set time to 23:59:59.
//                        $endDate->setTime(23, 59, 59);
//                    }
//                    $specialPrice->setEndDate($endDate);
//                }
//            }
//        }
    }

    /**
     * Process supplier of data array.
     *
     * @param array $data
     * @param ProductInterface $product
     *
     * @throws ProductDependencyNotFoundException
     */
    private function processSupplier(array $data, ProductInterface $product)
    {
        if (array_key_exists('supplier', $data)) {
            $supplier = null;
            if (isset($data['supplier']['id'])) {
                $supplierId = $data['supplier']['id'];
                /** @var AccountInterface $supplier */
                $supplier = $this->accountRepository->find($supplierId);
                if (!$supplier) {
                    throw new ProductDependencyNotFoundException($this->accountRepository->getClassName(), $supplierId);
                }
            }
            $product->setSupplier($supplier);
        }
    }

    /**
     * Processes categories of data array.
     *
     * @param array $data
     * @param ProductInterface $product
     */
    private function processCategories(array $data, ProductInterface $product)
    {
        if (array_key_exists('categories', $data)) {
            $product->getCategories()->clear();

            if (is_array($data['categories'])) {
                $get = function (CategoryInterface $category) {
                    return $category->getId();
                };

                $add = function ($categoryData) use ($product) {
                    return $this->addCategory($product, $categoryData);
                };

                $delete = function (CategoryInterface $category) use ($product) {
                    $product->removeCategory($category);

                    return true;
                };

                $this->processSubEntities(
                    $product->getCategories(),
                    $data['categories'],
                    $get,
                    $add,
                    null,
                    $delete
                );
            }
        }
    }

    /**
     * Adds a category to the given product.
     *
     * @param ProductInterface $product The product to add the price to
     * @param array $categoryData The array containing the data for the additional category
     *
     * @throws ProductDependencyNotFoundException
     *
     * @return bool
     */
    protected function addCategory(ProductInterface $product, $categoryData)
    {
        /** @var CategoryInterface $category */
        $category = $this->categoryRepository->find($categoryData['id']);

        if (!$category) {
            throw new ProductDependencyNotFoundException(
                $this->categoryRepository->getClassName(),
                $categoryData['id']
            );
        }

        $product->addCategory($category);

        $this->entityManager->persist($category);

        return true;
    }

    /**
     * Processes prices of data array.
     *
     * @param array $data
     * @param ProductInterface $product
     */
    private function processPrices(array $data, ProductInterface $product)
    {
        if (array_key_exists('prices', $data)) {
            $product->getPrices()->clear();

            if (is_array($data['prices'])) {
                if (isset($data['id']) && ($product->getId() == $data['id'])) {
                    $compare = function (ProductPrice $price, $data) {
                        if (isset($data['id'])) {
                            return $data['id'] == $price->getId();
                        } else {
                            return $this->priceHasChanged($data, $price);
                        }
                    };
                } else {
                    $compare = function (ProductPrice $price, $data) {
                        return $this->priceHasChanged($data, $price);
                    };
                }

                $add = function ($priceData) use ($product) {
                    return $this->addPrice($product, $priceData);
                };

                $update = function (ProductPrice $price, $matchedEntry) {
                    return $this->updatePrice($price, $matchedEntry);
                };

                $delete = function (ProductPrice $price) use ($product) {
                    return $this->removePrice($product, $price);
                };

                $this->compareEntitiesWithData(
                    $product->getPrices(),
                    $data['prices'],
                    $compare,
                    $add,
                    $update,
                    $delete
                );
            }
        }
    }

    /**
     * Checks if price of a product has changed.
     *
     * @param array $data
     * @param ProductPrice $price
     *
     * @return bool
     */
    private function priceHasChanged(array $data, ProductPrice $price)
    {
        $currencyNotChanged = isset($data['currency']) &&
            array_key_exists('name', $data['currency']) &&
            $data['currency']['name'] == $price->getCurrency()->getName();

        $valueNotChanged = array_key_exists('price', $data) &&
            $data['price'] == $price->getPrice();

        $minimumQuantityNotChanged = array_key_exists('minimumQuantity', $data) &&
            $data['minimumQuantity'] == $price->getMinimumQuantity();

        return $currencyNotChanged && $valueNotChanged && $minimumQuantityNotChanged;
    }

    /**
     * Updates the given price with the values from the given array.
     *
     * @param ProductPrice $price
     * @param array $matchedEntry
     *
     * @throws ProductDependencyNotFoundException
     *
     * @return bool
     */
    protected function updatePrice(ProductPrice $price, $matchedEntry)
    {
//        if (isset($matchedEntry['minimumQuantity'])) {
//            $price->setMinimumQuantity($matchedEntry['minimumQuantity']);
//        }
//        if (isset($matchedEntry['price'])) {
//            $price->setPrice($matchedEntry['price']);
//        }
//        if (isset($matchedEntry['currency'])) {
//            $currency = $this->currencyRepository->find($matchedEntry['currency']['id']);
//            if (!$currency) {
//                throw new ProductDependencyNotFoundException(
//                    self::$productPriceEntityName,
//                    $matchedEntry['currency']['id']
//                );
//            }
//            $price->setCurrency($currency);
//        }

        return true;
    }

    /**
     * Adds a price to the given product.
     *
     * @param ProductInterface $product The product to add the price to
     * @param array $priceData The array containing the data for the new price
     *
     * @return bool
     */
    protected function addPrice(ProductInterface $product, $priceData)
    {
        if (isset($priceData['price'])) {
            $this->productPriceManager->createNewProductPriceForCurrency(
                $product,
                $priceData['price'],
                $this->getProperty($priceData, 'minimumQuantity', 0),
                $priceData['currency']['id']
            );
        }

        return true;
    }

    /**
     * Removes a price from the given product.
     *
     * @param ProductInterface $product
     * @param ProductPrice $price
     *
     * @return bool
     */
    protected function removePrice(ProductInterface $product, ProductPrice $price)
    {
        // TODO:
//        $this->entityManager->remove($price);
        $product->removePrice($price);

        return true;
    }

    /**
     * Parses a comma separated string.
     * Trims all values and removes empty strings.
     *
     * @param string $string
     * @param int $maxLength
     *
     * @return null|string
     */
    public function parseCommaSeparatedString($string, $maxLength = self::MAX_SEARCH_TERMS_LENGTH)
    {
        $result = null;

        // Check if string is not empty.
        if (strlen(trim($string)) === 0) {
            return null;
        }

        // Lower case for case insensitivity.
        $string = mb_strtolower($string, 'UTF-8');

        // Convert string to array.
        $fields = explode(',', $string);

        // Validate and trim each field.
        $fields = array_map([$this, 'trimAndValidateField'], $fields);

        // Remove null entries.
        $fields = array_filter($fields);

        // Parse back into string.
        $result = implode(',', $fields);

        // Check if max length is exceeded.
        if (strlen($result) > $maxLength) {
            // Shorten to max-length.
            $result = substr($result, 0, $maxLength);

            $fields = explode(',', $result);

            // Remove last element.
            array_pop($fields);

            // Only one search field is provided and exceeds limit.
            if (count($fields) === 0) {
                return null;
            }

            // Parse back into string.
            $result = implode(',', $fields);
        }

        return $result;
    }

    /**
     * Returns callable setter for a given property on a product.
     *
     * @param string $propertyName
     * @param ProductInterface $product
     *
     * @throws ProductException
     *
     * @return array
     */
    private function retrieveProductSetter($propertyName, ProductInterface $product)
    {
        $setFunctionName = 'set' . ucfirst($propertyName);
        if (!method_exists($product, $setFunctionName)) {
            throw new ProductException(
                sprintf(
                    'Set function with name \'%s\' does not exist for product with class \'%s\'',
                    $setFunctionName,
                    get_class($product)
                )
            );
        }

        return [$product, $setFunctionName];
    }

    /**
     * Passes float value of data array with given key to callback function.
     * If data value is not numeric, default is set.
     * If data does not contain given key, nothing happens.
     *
     * @param callable $callback
     * @param array $data
     * @param string $key
     * @param mixed $default
     */
    private function setFloatValueOfDataArray(callable $callback, array $data, $key, $default = null)
    {
        if (array_key_exists($key, $data)) {
            $value = $default;
            if (is_numeric($data[$key])) {
                $value = floatval($data[$key]);
            }

            call_user_func($callback, $value);
        }
    }
}
