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

use Sulu\Bundle\ProductBundle\Entity\Product;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Api\Product as ApiProduct;

class ProductFactory implements ProductFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createEntity()
    {
        return new Product();
    }

    /**
     * {@inheritdoc}
     */
    public function createApiEntity(ProductInterface $product, $locale)
    {
        return new ApiProduct($product, $locale);
    }
}
