<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice;
use Sulu\Bundle\ProductBundle\Entity\Type;
use Sulu\Bundle\ProductBundle\Entity\TypeRepository;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductNotFoundException;
use Sulu\Bundle\ProductBundle\Traits\ArrayDataTrait;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authentication\UserRepositoryInterface;

/**
 * The ProductVariantManager contains functionality for create, update and delete product variants.
 */
class ProductVariantManager implements ProductVariantManagerInterface
{
    use ArrayDataTrait;

    /**
     * @var ProductManagerInterface
     */
    private $productManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductFactoryInterface
     */
    private $productFactory;

    /**
     * @var ProductAttributeManager
     */
    private $productAttributeManager;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var TypeRepository
     */
    private $productTypeRepository;

    /**
     * @var array
     */
    private $productTypesMap;

    /**
     * @var ProductPriceManager
     */
    private $productPriceManager;

    /**
     * @var UserRepositoryInterface
     */
    private $userRepository;

    /**
     * @param EntityManagerInterface $entityManager
     * @param ProductManagerInterface $productManager
     * @param ProductRepositoryInterface $productRepository
     * @param ProductFactoryInterface $productFactory
     * @param ProductAttributeManager $productAttributeManager
     * @param TypeRepository $typeRepository
     * @param ProductPriceManager $productPriceManager
     * @param UserRepositoryInterface $userRepository
     * @param array $productTypesMap
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ProductManagerInterface $productManager,
        ProductRepositoryInterface $productRepository,
        ProductFactoryInterface $productFactory,
        ProductAttributeManager $productAttributeManager,
        TypeRepository $typeRepository,
        ProductPriceManager $productPriceManager,
        UserRepositoryInterface $userRepository,
        array $productTypesMap
    ) {
        $this->entityManager = $entityManager;
        $this->productManager = $productManager;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->productAttributeManager = $productAttributeManager;
        $this->productTypeRepository = $typeRepository;
        $this->productPriceManager = $productPriceManager;
        $this->userRepository = $userRepository;
        $this->productTypesMap = $productTypesMap;
    }

    /**
     * {@inheritdoc}
     */
    public function createVariant($parentId, array $variantData, $locale, $userId)
    {
        // Check if parent product exists.
        $parent = $this->productRepository->findById($parentId);
        if (!$parent) {
            throw new ProductNotFoundException($parentId);
        }

        $parent->setNumberOfVariants($parent->getNumberOfVariants() + 1);

        // Create variant product by setting variant data.
        /** @var ProductInterface $variant */
        $variant = $this->productFactory->createEntity();
        $this->entityManager->persist($variant);

        // Set parent.
        $variant->setParent($parent);
        $variant->setType($this->getTypeVariantReference());
        $variant->setStatus($parent->getStatus());
        $variant->setCreator($this->getUserReferenceById($userId));
        $variant->setCreated(new \DateTime());

        // Set data to variant.
        $this->mapDataToVariant($variant, $variantData, $locale, $userId);

        return $variant;
    }

    /**
     * {@inheritdoc}
     */
    public function updateVariant($variantId, array $variantData, $locale, $userId)
    {
        // Check if parent product exists.
        $variant = $this->productRepository->findById($variantId);
        if (!$variant) {
            throw new ProductNotFoundException($variantId);
        }

        // Check if product is variant.
        if ($variant->getType()->getId() !== (int) $this->productTypesMap['PRODUCT_VARIANT']) {
            throw new ProductException('Product is no variant and therefore cannot be updated');
        }

        // Set data to variant.
        $this->mapDataToVariant($variant, $variantData, $locale, $userId);

        return $variant;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteVariant($variantId)
    {
        $variant = $this->productRepository->findById($variantId);

        if (!$variant) {
            throw new ProductNotFoundException($variantId);
        }
        if ($variant->getType()->getId() !== (int) $this->productTypesMap['PRODUCT_VARIANT']) {
            throw new ProductException('Product is no variant and therefore cannot be deleted');
        }

        $parent = $variant->getParent();
        $parent->setNumberOfVariants($parent->getNumberOfVariants() - 1);

        $this->entityManager->remove($variant);

        return $variant;
    }

    /**
     * Maps all the data to a given variant for the given locale.
     *
     * @param ProductInterface $variant
     * @param array $variantData
     * @param string $locale
     * @param int $userId
     */
    private function mapDataToVariant(ProductInterface $variant, array $variantData, $locale, $userId)
    {
        $productTranslation = $this->productManager->retrieveOrCreateProductTranslationByLocale($variant, $locale);
        $productTranslation->setName($this->getProperty($variantData, 'name'));

        $variant->setNumber($this->getProperty($variantData, 'number'));

        $this->processAttributes($variant, $variantData, $locale);
        $this->processPrices($variant, $variantData);

        // Check if entity is going to be updated.
        $this->entityManager->getUnitOfWork()->computeChangeSets();
        if ($this->entityManager->getUnitOfWork()->isScheduledForUpdate($variant)) {
            $variant->setChanger($this->getUserReferenceById($userId));
            $variant->setChanged(new \DateTime());
        }
    }

    /**
     * Adds variant attributes to variant.
     *
     * @param ProductInterface $variant
     * @param array $variantData
     * @param string $locale
     *
     * @throws ProductException
     */
    private function processAttributes(ProductInterface $variant, array $variantData, $locale)
    {
        $parent = $variant->getParent();

        // Number of attributes in variantData and parents variant-attributes need to match and must not be 0.
        $sizeOfVariantAttributes = count($this->getProperty($variantData, 'attributes'));
        $sizeOfParentAttributes = $parent->getVariantAttributes()->count();
        if (!$sizeOfVariantAttributes
            || !$sizeOfParentAttributes
            || $sizeOfVariantAttributes != $sizeOfParentAttributes
        ) {
            throw new ProductException('Invalid number of attributes for variant provided.');
        }

        $attributesDataCopy = $variantData['attributes'];

        // For all variants of parent, find corresponding attribute in variant-data.
        foreach ($parent->getVariantAttributes() as $variantAttribute) {
            foreach ($attributesDataCopy as $index => $attributeData) {
                if ($variantAttribute->getId() === $attributeData['attributeId']) {
                    // Update or create ProductAttribute for variant.
                    $this->productAttributeManager->updateOrCreateProductAttributeForProduct(
                        $variant,
                        $variantAttribute,
                        $attributeData,
                        $locale
                    );

                    unset($attributesDataCopy[$index]);
                }
            }
        }

        // Not all necessary variant attributes were defined in data array.
        if (count($attributesDataCopy)) {
            throw new ProductException('Invalid attributes for variant provided.');
        }
    }

    /**
     * Adds or updates price information for a variant.
     *
     * @param ProductInterface $variant
     * @param array $variantData
     */
    private function processPrices(ProductInterface $variant, array $variantData)
    {
        if (!count($variantData['prices'])) {
            return;
        }

        $currentPrices = iterator_to_array($variant->getPrices());
        foreach ($variantData['prices'] as $price) {
            $matchingEntity = $this->retrievePriceByCurrency($currentPrices, $price['currency']['id']);

            // Create new price if no match was found.
            if (!$matchingEntity) {
                $this->productPriceManager->createNewProductPriceForCurrency(
                    $variant,
                    $price['price'],
                    0,
                    $price['currency']['id']
                );
            } else {
                // Otherwise just update price.
                $matchingEntity->setPrice($price['price']);
            }
        }

        // The following prices were not matched and therefore have to be deleted.
        foreach ($currentPrices as $price) {
            $this->entityManager->remove($price);
        }
    }

    /**
     * Returns price by currency id. If found, the ProductPrice entity is removed
     * from the prices array.
     *
     * @param ProductPrice[] $prices
     * @param $currencyId
     *
     * @return null|ProductPrice
     */
    private function retrievePriceByCurrency(array &$prices, $currencyId)
    {
        /** @var ProductPrice $price */
        foreach ($prices as $index => $price) {
            if ($currencyId === $price->getCurrency()->getId()) {
                unset($prices[$index]);

                return $price;
            }
        }

        return null;
    }

    /**
     * Returns a reference to a user by providing a user-id.
     *
     * @param int $userId
     *
     * @return UserInterface
     */
    private function getUserReferenceById($userId)
    {
        return $this->entityManager->getReference($this->userRepository->getClassName(), $userId);
    }

    /**
     * Returns the product type of a variant.
     *
     * @return Type
     */
    private function getTypeVariantReference()
    {
        return $this->entityManager->getReference(Type::class, $this->productTypesMap['PRODUCT_VARIANT']);
    }
}
