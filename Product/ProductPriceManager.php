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

use JMS\Serializer\Annotation\Groups;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\PricingBundle\Pricing\PriceFormatter;

class ProductPriceManager implements ProductPriceManagerInterface
{
    protected $defaultCurrency;

    /**
     * @var PriceFormatter
     */
    protected $priceFormatter;

    /**
     * @param string $defaultCurrency
     * @param PriceFormatter $priceFormatter
     */
    public function __construct(
        $defaultCurrency,
        PriceFormatter $priceFormatter
    ) {
        $this->defaultCurrency = $defaultCurrency;
        $this->priceFormatter = $priceFormatter;
    }

    /**
     * Returns the bulk price for a certain quantity of the product by a given currency
     *
     * @param ProductInterface $product
     * @param $quantity
     * @param null|string $currency
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
     * @param null|string $currency
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
     * Returns the special price for the product by a given currency
     *
     * @param ProductInterface $product
     * @param null|string $currency
     *
     * @return null|\Sulu\Bundle\ProductBundle\Entity\ProductPrice
     */
    public function getSpecialPriceForCurrency(ProductInterface $product, $currency = null)
    {
        $currency = $currency ?: $this->defaultCurrency;
        $specialPrices = $product->getSpecialPrices();

        if ($specialPrices) {
            foreach ($specialPrices as $specialPriceEntity) {
                if ($specialPriceEntity->getCurrency()->getCode() == $currency) {
                    $startDate = $specialPriceEntity->getStartDate();
                    $endDate = $specialPriceEntity->getEndDate();
                    $now = new \DateTime();
                    if (($now >= $startDate && $now <= $endDate) ||
                        ($now >= $startDate && empty($endDate)) ||
                        (empty($startDate) && empty($endDate))
                    ) {
                        return $specialPriceEntity;
                    }

                    return null;
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
    public function getFormattedPrice($price, $symbol = 'EUR', $locale = null)
    {
        $location = PriceFormatter::CURRENCY_LOCATION_NONE;
        if (!empty($symbol)) {
            $location = PriceFormatter::CURRENCY_LOCATION_SUFFIX;
        }

        return $this->priceFormatter->format(
            (float)$price,
            null,
            $locale,
            $symbol,
            $location
        );
    }
}
