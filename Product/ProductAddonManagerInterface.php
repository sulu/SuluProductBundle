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

use Sulu\Bundle\ProductBundle\Api\Addon;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;

interface ProductAddonManagerInterface
{
    /**
     * @param int $id
     * @param string $locale
     *
     * @return Addon[]
     */
    public function findAddonsByProductIdAndLocale($id, $locale);

    /**
     * @param int $id
     * @param string $locale
     *
     * @return Addon
     */
    public function findAddonById($id, $locale);

    /**
     * @param int $product
     * @param int $addon
     * @param string $locale
     * @param array $prices
     *
     * @return Addon
     */
    public function saveProductAddon($product, $addon, $locale, array $prices = null);

    /**
     * @param int $productId
     * @param int $addonId
     */
    public function deleteProductAddon($productId, $addonId);

    /**
     * @param int $id
     * @param bool $flush
     */
    public function deleteById($id, $flush = false);
}
