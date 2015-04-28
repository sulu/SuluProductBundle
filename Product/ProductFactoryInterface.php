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

use Sulu\Bundle\ProductBundle\Api\ApiProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;

interface ProductFactoryInterface
{
    /**
     * Creates a new entity
     *
     * @return ProductInterface
     */
    public function createEntity();

    /**
     * Creates a new api entity
     *
     * @param ProductInterface $product
     * @param string $locale
     *
     * @return ApiProductInterface
     */
    public function createApiEntity(ProductInterface $product, $locale);
}
