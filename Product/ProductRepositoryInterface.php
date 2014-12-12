<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Sulu\Bundle\ProductBundle\Entity\ProductInterface;

/**
 * The interface for the ProductRepository
 * @package Sulu\Bundle\ProductBundle\Product
 */
interface ProductRepositoryInterface
{
    /**
     * Finds the product with the given ID
     * @param integer $id The id of the product
     * @return ProductInterface
     */
    public function findById($id);

    /**
     * Finds the product with the given ID in the given language
     * @param int $id The id of the product
     * @param string $locale The locale of the product to load
     * @return ProductInterface
     */
    public function findByIdAndLocale($id, $locale);

    /**
     * Returns all products in the given locale
     * @param string $locale The locale of the product to load
     * @return ProductInterface[]
     */
    public function findAllByLocale($locale);

    /**
     * Returns all products with the given locale and filters
     * @param string $locale The locale to load
     * @param array $filter The filters for loading
     * @return ProductInterface[]
     */
    public function findByLocaleAndFilter($locale, array $filter);

    /**
     * Returns all products with the given locale and ids
     * @param string $locale The locale to load
     * @param array $ids
     * @return ProductInterface[]
     */
    public function findByLocaleAndIds($locale, array $ids);

} 
