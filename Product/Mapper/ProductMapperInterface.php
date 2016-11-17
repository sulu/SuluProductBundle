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

use Sulu\Bundle\ProductBundle\Entity\ProductInterface;

/**
 * Interface for product mapper service, which is responsible for mapping data to a product.
 */
interface ProductMapperInterface
{
    /**
     * Maps data to a given product.
     *
     * @param ProductInterface $product
     * @param array $data
     * @param string $locale
     *
     * @return array
     */
    public function map(ProductInterface $product, array $data, $locale);

    /**
     * Get product data of a product by locale.
     *
     * @param ProductInterface $product
     * @param string $locale
     *
     * @return array
     */
    public function get(ProductInterface $product, $locale);
}
