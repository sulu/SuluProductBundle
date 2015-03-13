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

class ProductPriceManager implements ProductPriceManagerInterface
{
    protected $defaultCurrency;

    /**
     * @param $defaultCurrency
     */
    public function __construct(
        $defaultCurrency
    )
    {
        $this->defaultCurrency = $defaultCurrency;
    }

    /**
     * Returns the bulk price for a certain quantity of the product by a given currency
     *
     * @param ProductInterface $product
     * @param $quantity
     * @param null $currency
     *
     * @return null|\Sulu\Bundle\ProductBundle\Entity\ProductPrice
     */
    public function getBulkPriceForCurrency(ProductInterface $product, $quantity, $currency = null)
    {
        $currency = $currency ?: $this->defaultCurrency;

        $bulkPrice = null;
        if ($prices = $product->getPrices()) {
            $bestDifference = PHP_INT_MAX;
            foreach ($prices as $price) {
                if ($price->getCurrency()->getCode() == $currency &&
                    $price->getMinimumQuantity() <= $quantity &&
                    ($quantity - $price->getMinimumQuantity()) < $bestDifference
                ) {
                    $bestDifference = $quantity - $price->getMinimumQuantity();
                    $bulkPrice = $price;
                }
            }
        }

        return $bulkPrice;
    }

    /**
     * Returns the base prices for the product by a given currency
     *
     * @param ProductInterface $product
     * @param null $currency
     *
     * @return null|\Sulu\Bundle\ProductBundle\Entity\ProductPrice
     */
    public function getBasePriceForCurrency(ProductInterface $product, $currency = null)
    {
        $currency = $currency ?: $this->defaultCurrency;
        if ($prices = $product->getPrices()) {
            foreach ($prices as $price) {
                if ($price->getCurrency()->getCode() == $currency && $price->getMinimumQuantity() == 0) {
                    return $price;
                }
            }
        }

        return null;
    }

    /**
     * Helper function to get a formatted price for a given currency and locale
     *
     * @param Integer $price
     * @param String $symbol
     * @param String $locale
     *
     * @return String price
     * @Groups({"cart"})
     */
    public function getFormattedPrice($price, $symbol = 'EUR', $locale = 'de')
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, $symbol);

        return $formatter->format((float)$price);
    }
}
