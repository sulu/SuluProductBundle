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
 * The interface for the product manager
 * @package Sulu\Bundle\ProductBundle\Product
 */
interface ProductManagerInterface
{
    /**
     * Returns the product with the given ID and locale
     * @param int $id The id of the product to load
     * @param string $locale The locale to load
     * @return ProductInterface
     */
    public function findByIdAndLocale($id, $locale);

    /**
     * Returns all products in the given locale
     * @param string $locale
     * @param array $filter
     * @return ProductInterface[]
     */
    public function findAllByLocale($locale, $filter = array());
} 
