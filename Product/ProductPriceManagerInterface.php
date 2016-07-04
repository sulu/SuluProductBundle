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
use Sulu\Bundle\ProductBundle\Entity\ProductPrice;

interface ProductPriceManagerInterface
{
    /**
     * Returns the bulk price for a certain quantity of the product by a given currency.
     *
     * @param ProductInterface $product
     * @param float $quantity
     * @param null|string $currency
     *
     * @return null|ProductPrice
     */
    public function getBulkPriceForCurrency(ProductInterface $product, $quantity, $currency = null);

    /**
     * Returns the base prices for the product by a given currency.
     *
     * @param ProductInterface $product
     * @param null|string $currency
     *
     * @return null|ProductPrice
     */
    public function getBasePriceForCurrency(ProductInterface $product, $currency = null);

    /**
     * Helper function to get a formatted price for a given currency and locale.
     *
     * @param int $price
     * @param string $symbol
     * @param string $locale
     *
     * @return string price
     */
    public function getFormattedPrice($price, $symbol = 'EUR', $locale = null);

    /**
     * Returns the special price for the product by a given currency.
     *
     * @param ProductInterface $product
     * @param null|string $currency
     *
     * @return null|ProductPrice
     */
    public function getSpecialPriceForCurrency(ProductInterface $product, $currency = null);

}
