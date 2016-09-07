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
 * The interface for the ProductRepository.
 */
interface ProductRepositoryInterface
{
    /**
     * Finds the product with the given ID.
     *
     * @param int $id The id of the product
     *
     * @return ProductInterface
     */
    public function findById($id);

    /**
     * Finds the product with the given ID in the given language.
     *
     * @param int $id The id of the product
     * @param string $locale The locale of the product to load
     *
     * @return ProductInterface
     */
    public function findByIdAndLocale($id, $locale);

    /**
     * Returns all products in the given locale.
     *
     * @param string $locale The locale of the product to load
     *
     * @return ProductInterface[]
     */
    public function findAllByLocale($locale);

    /**
     * Returns all products with the given locale and filters.
     *
     * @param string $locale The locale to load
     * @param array $filter The filters for loading
     *
     * @return ProductInterface[]
     */
    public function findByLocaleAndFilter($locale, array $filter);

    /**
     * Returns all products with the given locale and ids.
     *
     * @param string $locale The locale to load
     * @param array $ids
     *
     * @return ProductInterface[]
     */
    public function findByLocaleAndIds($locale, array $ids);

    /**
     * @param array $tags
     * @param string $locale
     *
     * @return ProductInterface[]
     */
    public function findByTags(array $tags, $locale);

    /**
     * @param int $categoryId
     * @param string $locale
     *
     * @return ProductInterface[]
     */
    public function findByCategoryId($categoryId, $locale);

    /**
     * @param string $locale
     * @param array $categoryIds
     * @param array $tags
     *
     * @return ProductInterface[]
     */
    public function findByCategoryIdsAndTags($locale, array $categoryIds = [], array $tags = []);

    /**
     * Returns all products for the given internal number.
     *
     * @param string $internalItemNumber The internal number of the product to load
     *
     * @return ProductInterface[]
     */
    public function findByInternalItemNumber($internalItemNumber);

    /**
     * Returns all simple products in the given locale for the given number.
     *
     * @param string $locale The locale of the product to load
     * @param string $internalItemNumber The number of the product to load
     *
     * @return ProductInterface[]
     */
    public function findByLocaleAndInternalItemNumber($locale, $internalItemNumber);
}
