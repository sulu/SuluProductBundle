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

use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;

/**
 * Interface for Manager that handles routes of product bundle.
 */
interface ProductRouteManagerInterface
{
    /**
     * Handles creation or update of a product-route.
     *
     * @param ProductTranslation $productTranslation
     * @param string|null $path
     */
    public function saveRoute(ProductTranslation $productTranslation, $path = null);

    /**
     * Checks if custom routing is enabled for products.
     *
     * @return bool
     */
    public function isRoutingEnabled();
}
