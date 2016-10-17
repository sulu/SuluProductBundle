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

use Sulu\Bundle\ProductBundle\Entity\ProductInterface;

/**
 * This interface contains all methods a ProductVariantManager needs to implement.
 */
interface ProductVariantManagerInterface
{
    /**
     * Adds a variant to a specific product.
     *
     * @param int $parentId The id of the product, to which the variant is added
     * @param array $variantData Data that is used for creating variant
     * @param string $locale The locale to load
     * @param int $userId
     *
     * @return ProductInterface
     */
    public function createVariant($parentId, array $variantData, $locale, $userId);

    /**
     * Updates data of an existing variant.
     *
     * @param int $variantId The id of the product, which is added to the other as a variant
     * @param array $variantData Data that overwrites existing data of product
     * @param string $locale The locale to load
     * @param int $userId
     *
     * @return ProductInterface
     */
    public function updateVariant($variantId, array $variantData, $locale, $userId);

    /**
     * Deletes the given variant from database.
     *
     * @param int $variantId The id of the product, which is removed
     *
     * @return ProductInterface
     */
    public function deleteVariant($variantId);
}
