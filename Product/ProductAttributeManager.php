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

use Doctrine\Common\Persistence\ObjectManager;
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\AttributeValue;
use Sulu\Bundle\ProductBundle\Entity\AttributeValueRepository;
use Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation;
use Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslationRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductAttribute;
use Sulu\Bundle\ProductBundle\Entity\ProductAttributeRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;

/**
 * This manager is responsible for handling product attributes.
 */
class ProductAttributeManager
{
    /**
     * @var ObjectManager
     */
    private $entityManager;

    /**
     * @var ProductAttributeRepository
     */
    private $productAttributeRepository;

    /**
     * @var AttributeValueRepository
     */
    private $attributeValueRepository;

    /**
     * @var AttributeValueTranslationRepository
     */
    private $attributeValueTranslationRepository;

    /**
     * @param ObjectManager $entityManager
     * @param ProductAttributeRepository $productAttributeRepository
     * @param AttributeValueRepository $attributeValueRepository
     * @param AttributeValueTranslationRepository $attributeValueTranslationRepository
     */
    public function __construct(
        ObjectManager $entityManager,
        ProductAttributeRepository $productAttributeRepository,
        AttributeValueRepository $attributeValueRepository,
        AttributeValueTranslationRepository $attributeValueTranslationRepository
    ) {
        $this->entityManager = $entityManager;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->attributeValueRepository = $attributeValueRepository;
        $this->attributeValueTranslationRepository = $attributeValueTranslationRepository;
    }

    /**
     * Creates a new ProductAttribute relation.
     *
     * @param Attribute $attribute
     * @param ProductInterface $product
     * @param AttributeValue $attributeValue
     *
     * @return ProductAttribute
     */
    public function createProductAttribute(
        Attribute $attribute,
        ProductInterface $product,
        AttributeValue $attributeValue
    ) {
        $productAttribute = $this->productAttributeRepository->createNew();
        $productAttribute->setAttribute($attribute);
        $productAttribute->setProduct($product);
        $productAttribute->setAttributeValue($attributeValue);
        $this->entityManager->persist($productAttribute);

        $product->addProductAttribute($productAttribute);

        return $productAttribute;
    }

    /**
     * Creates a new attribute value and its translation in the specified locale.
     *
     * @param Attribute $attribute
     * @param string $value
     * @param string $locale
     *
     * @return AttributeValue
     */
    public function createAttributeValue(Attribute $attribute, $value, $locale)
    {
        $attributeValue = $this->attributeValueRepository->createNew();
        $attributeValue->setAttribute($attribute);
        $this->entityManager->persist($attributeValue);
        $attribute->addValue($attributeValue);
        $this->setOrCreateAttributeValueTranslation($attributeValue, $value, $locale);

        return $attributeValue;
    }

    /**
     * Checks if AttributeValue already contains a translation in given locale or creates a new one.
     *
     * @param AttributeValue $attributeValue
     * @param string $value
     * @param string $locale
     *
     * @return AttributeValueTranslation
     */
    public function setOrCreateAttributeValueTranslation(AttributeValue $attributeValue, $value, $locale)
    {
        // Check if translation already exists for given locale.
        $attributeValueTranslation = null;
        /** @var AttributeValueTranslation $translation */
        foreach ($attributeValue->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                $attributeValueTranslation = $translation;
            }
        }
        if (!$attributeValueTranslation) {
            // Create a new attribute value translation.
            $attributeValueTranslation = $this->attributeValueTranslationRepository->createNew();
            $this->entityManager->persist($attributeValueTranslation);
            $attributeValueTranslation->setLocale($locale);
            $attributeValueTranslation->setAttributeValue($attributeValue);
            $attributeValue->addTranslation($attributeValueTranslation);
        }
        $attributeValueTranslation->setName($value);

        return $attributeValueTranslation;
    }

    /**
     * Removes attribute value translation in given locale from given attribute.
     *
     * @param AttributeValue $attributeValue
     * @param string $locale
     */
    public function removeAttributeValueTranslation(AttributeValue $attributeValue, $locale)
    {
        // Check if translation already exists for given locale.
        $attributeValueTranslation = $this->retrieveAttributeValueTranslationByLocale($attributeValue, $locale);
        if ($attributeValueTranslation) {
            $attributeValue->removeTranslation($attributeValueTranslation);
            $this->entityManager->remove($attributeValueTranslation);
        }
    }

    /**
     * Removes all attribute value translations from given attribute.
     *
     * @param AttributeValue $attributeValue
     */
    public function removeAllAttributeValueTranslations(AttributeValue $attributeValue)
    {
        // Check if translation already exists for given locale.
        /** @var AttributeValueTranslation $attributeValueTranslation */
        foreach ($attributeValue->getTranslations() as $attributeValueTranslation) {
            $attributeValue->removeTranslation($attributeValueTranslation);
            $this->entityManager->remove($attributeValueTranslation);
        }
    }

    /**
     * Updates or creates a the product attribute relation for the given product.
     *
     * @param ProductInterface $product
     * @param Attribute $attribute
     * @param array $attributeData
     * @param string $locale
     */
    public function updateOrCreateProductAttributeForProduct(
        ProductInterface $product,
        Attribute $attribute,
        array $attributeData,
        $locale
    ) {
        // Check if ProductAttribute already exists for attribute
        $existingProductAttribute = $this->retrieveProductAttributeByAttribute(
            $product,
            $attribute
        );

        // If product-attribute already exists we just need to update the translation.
        if ($existingProductAttribute) {
            $this->setOrCreateAttributeValueTranslation(
                $existingProductAttribute->getAttributeValue(),
                $attributeData['attributeValueName'],
                $locale
            );

            return;
        }

        // Create new AttributeValue and ProductAttribute.
        $attributeValue = $this->createAttributeValue(
            $attribute,
            $attributeData['attributeValueName'],
            $locale
        );
        $this->createProductAttribute(
            $attribute,
            $product,
            $attributeValue
        );
    }

    /**
     * Searches products product-attributes for given attribute.
     *
     * @param ProductInterface $product
     * @param Attribute $attribute
     *
     * @return ProductAttribute|null
     */
    public function retrieveProductAttributeByAttribute(ProductInterface $product, Attribute $attribute)
    {
        foreach ($product->getProductAttributes() as $productAttribute) {
            if ($productAttribute->getAttribute()->getId() === $attribute->getId()) {
                return $productAttribute;
            }
        }

        return null;
    }

    /**
     * Returns the translation in given locale for the given AttributeValue.
     *
     * @param AttributeValue $attributeValue
     * @param string $locale
     *
     * @return AttributeValueTranslation|null
     */
    private function retrieveAttributeValueTranslationByLocale(AttributeValue $attributeValue, $locale)
    {
        foreach ($attributeValue->getTranslations() as $attributeValueTranslation) {
            if ($attributeValueTranslation->getLocale() === $locale) {
                return $attributeValueTranslation;
            }
        }

        return null;
    }
}
