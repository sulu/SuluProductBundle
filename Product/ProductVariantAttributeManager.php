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

use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Product\Exception\AttributeNotFoundException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductException;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductNotFoundException;
use Sulu\Bundle\ProductBundle\Traits\ArrayDataTrait;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineJoinDescriptor;

/**
 * This manager is responsible for handling product attributes.
 */
class ProductVariantAttributeManager
{
    use ArrayDataTrait;

    private static $attributeEntityName = 'SuluProductBundle:Attribute';
    private static $attributeTranslationEntityName = 'SuluProductBundle:AttributeTranslation';
    private static $productEntityName = 'SuluProductBundle:Product';

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param AttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        AttributeRepositoryInterface $attributeRepository
    ) {
        $this->productRepository = $productRepository;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * Returns all field-descriptors for variant attributes.
     *
     * @param string $locale
     *
     * @return DoctrineFieldDescriptor[]
     */
    public function retrieveFieldDescriptors($locale)
    {
        $fieldDescriptors = [];

        $fieldDescriptors['id'] = new DoctrineFieldDescriptor(
            'id',
            'id',
            static::$attributeEntityName,
            null,
            [
                static::$attributeEntityName => new DoctrineJoinDescriptor(
                    static::$attributeEntityName,
                    static::$productEntityName . '.variantAttributes'
                ),
            ],
            true,
            false,
            'integer'
        );

        $fieldDescriptors['name'] = new DoctrineFieldDescriptor(
            'name',
            'name',
            static::$attributeTranslationEntityName,
            null,
            [
                static::$attributeEntityName => new DoctrineJoinDescriptor(
                    static::$attributeEntityName,
                    static::$productEntityName . '.variantAttributes'
                ),
                static::$attributeTranslationEntityName => new DoctrineJoinDescriptor(
                    static::$attributeTranslationEntityName,
                    static::$attributeEntityName . '.translations',
                    static::$attributeTranslationEntityName . '.locale = \'' . $locale . '\'',
                    DoctrineJoinDescriptor::JOIN_METHOD_INNER
                ),
            ],
            false,
            true,
            'string'
        );

        return $fieldDescriptors;
    }

    /**
     * Creates a new relation between variant and attribute.
     *
     * @param int $productId
     * @param array $requestData
     *
     * @throws AttributeNotFoundException
     * @throws ProductNotFoundException
     *
     * @return ProductInterface
     */
    public function createVariantAttributeRelation($productId, array $requestData)
    {
        $variant = $this->retrieveProductById($productId);
        $attribute = $this->retrieveAttributeById($this->getProperty($requestData, 'attributeId'));

        // Only add if relation does not already exists.
        if (!$variant->getVariantAttributes()->contains($attribute)) {
            $variant->addVariantAttribute($attribute);
        }

        return $variant;
    }

    /**
     * Removes relation between variant and attribute.
     *
     * @param int $productId
     * @param int $attributeId
     *
     * @throws ProductException
     *
     * @return ProductInterface
     */
    public function removeVariantAttributeRelation($productId, $attributeId)
    {
        $variant = $this->retrieveProductById($productId);
        $attribute = $this->retrieveAttributeById($attributeId);

        if (!$variant->getVariantAttributes()->contains($attribute)) {
            throw new ProductException('Variant does not have relation to attribute');
        }

        $variant->removeVariantAttribute($attribute);

        return $variant;
    }

    /**
     * Fetches attribute from db. If not found an exception is thrown.
     *
     * @param int $attributeId
     *
     * @throws AttributeNotFoundException
     *
     * @return Attribute
     */
    private function retrieveAttributeById($attributeId)
    {
        $attribute = $this->attributeRepository->find($attributeId);
        if (!$attribute) {
            throw new AttributeNotFoundException($attributeId);
        }

        return $attribute;
    }

    /**
     * Fetches product from db. If not found an exception is thrown.
     *
     * @param int $productId
     *
     * @throws ProductNotFoundException
     *
     * @return ProductInterface
     */
    private function retrieveProductById($productId)
    {
        // Fetch product.
        $product = $this->productRepository->find($productId);
        if (!$product) {
            throw new ProductNotFoundException($productId);
        }

        return $product;
    }
}
