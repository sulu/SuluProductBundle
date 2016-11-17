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

use Sulu\Bundle\ProductBundle\Entity\Addon;
use Sulu\Bundle\ProductBundle\Entity\AddonPrice;
use Sulu\Bundle\ProductBundle\Entity\CurrencyRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductPrice;
use Sulu\Bundle\ProductBundle\Entity\SpecialPrice;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductDependencyNotFoundException;
use Sulu\Bundle\ProductBundle\Util\PriceFormatter;

/**
 * Product price manager is responsible for creating and returning the correct product prices.
 */
class ProductPriceManager implements ProductPriceManagerInterface
{
    /**
     * @var string
     */
    protected $defaultCurrency;

    /**
     * @var PriceFormatter
     */
    protected $priceFormatter;

    /**
     * @var CurrencyRepository
     */
    private $currencyRepository;

    /**
     * @param string $defaultCurrency
     * @param PriceFormatter $priceFormatter
     * @param CurrencyRepository $currencyRepository
     */
    public function __construct(
        $defaultCurrency,
        PriceFormatter $priceFormatter,
        CurrencyRepository $currencyRepository
    ) {
        $this->defaultCurrency = $defaultCurrency;
        $this->priceFormatter = $priceFormatter;
        $this->currencyRepository = $currencyRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function createNewProductPriceForCurrency(
        ProductInterface $product,
        $priceValue,
        $minimumQuantity = 0.0,
        $currencyId = null
    ) {
        // Fetch currency.
        if (!$currencyId) {
            $currency = $this->currencyRepository->findByCode($this->defaultCurrency);
        } else {
            $currency = $this->currencyRepository->find($currencyId);
        }

        if (!$currency) {
            throw new ProductDependencyNotFoundException($this->currencyRepository->getClassName(), $currencyId);
        }

        $productPrice = new ProductPrice();
        $productPrice->setMinimumQuantity($minimumQuantity);
        $productPrice->setPrice($priceValue);
        $productPrice->setProduct($product);
        $productPrice->setCurrency($currency);
        $product->addPrice($productPrice);

        return $productPrice;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getSpecialPriceForCurrency(ProductInterface $product, $currency = null)
    {
        $currency = $currency ?: $this->defaultCurrency;
        $specialPrices = $product->getSpecialPrices();

        // Check if any special prices are set.
        if (!$specialPrices) {
            return null;
        }

        foreach ($specialPrices as $specialPriceEntity) {
            // Find special price with matching currency.
            if ($specialPriceEntity->getCurrency()->getCode() == $currency) {
                // Check if special price is still valid.
                if ($this->isValidSpecialPrice($specialPriceEntity)) {
                    return $specialPriceEntity;
                }

                break;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAddonPriceForCurrency(Addon $addon, $currency = null)
    {
        $currency = $currency ?: $this->defaultCurrency;

        $addonPrices = $addon->getAddonPrices();

        // Check if any addon prices are set.
        if (!$addonPrices) {
            return null;
        }

        /** @var AddonPrice $addonPrice */
        foreach ($addonPrices as $addonPrice) {
            if ($addonPrice->getCurrency()->getCode() === $currency) {
                return $addonPrice;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormattedPrice($price, $symbol = 'EUR', $locale = null)
    {
        $location = PriceFormatter::CURRENCY_LOCATION_NONE;
        if (!empty($symbol)) {
            $location = PriceFormatter::CURRENCY_LOCATION_SUFFIX;
        }

        return $this->priceFormatter->format(
            (float) $price,
            null,
            $locale,
            $symbol,
            $location
        );
    }

    /**
     * Checks if a special price is still valid by today.
     *
     * @param SpecialPrice $specialPrice
     *
     * @return bool
     */
    private function isValidSpecialPrice(SpecialPrice $specialPrice)
    {
        $startDate = $specialPrice->getStartDate();
        $endDate = $specialPrice->getEndDate();
        $now = new \DateTime();

        // Check if special price is stil valid.
        if (($now >= $startDate && $now <= $endDate) ||
            ($now >= $startDate && empty($endDate)) ||
            (empty($startDate) && empty($endDate))
        ) {
            return true;
        }

        return false;
    }
}
