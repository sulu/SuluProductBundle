<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Util;

use NumberFormatter;
use Sulu\Bundle\ProductBundle\Exception\PriceFormatterException;

/**
 * Util for formatting prices.
 */
class PriceFormatter
{
    // Define constants for location of currency string.
    const CURRENCY_LOCATION_PREFIX = 'prefix';
    const CURRENCY_LOCATION_SUFFIX = 'suffix';
    const CURRENCY_LOCATION_NONE = 'none';
    const DEFAULT_NUMBER_OF_DITIS = 2;

    /**
     * @var string
     */
    protected $defaultLocale;

    /**
     * @var int
     */
    protected $defaultDigits;

    /**
     * @param string $defaultLocale
     * @param int $defaultDigits
     */
    public function __construct($defaultLocale, $defaultDigits = self::DEFAULT_NUMBER_OF_DITIS)
    {
        $this->defaultLocale = $defaultLocale;
        $this->defaultDigits = $defaultDigits;
    }

    /**
     * @param float $price
     * @param int|null $digits
     * @param string|null $locale
     * @param string|null $currency
     * @param string $currencyPosition
     *
     * @throws PriceFormatterException
     *
     * @return string
     */
    public function format(
        $price,
        $digits = null,
        $locale = null,
        $currency = null,
        $currencyPosition = self::CURRENCY_LOCATION_NONE
    ) {
        if (is_null($locale)) {
            $locale = $this->defaultLocale;
        }

        if (is_null($digits)) {
            $digits = $this->defaultDigits;
        }

        $formatter = $this->getFormatter($locale, $digits);
        $formattedPrice = $formatter->format($price);

        if ($currencyPosition != self::CURRENCY_LOCATION_NONE && is_null($currency)) {
            throw new PriceFormatterException('currency must be set, if location is set');
        }

        switch ($currencyPosition) {
            case self::CURRENCY_LOCATION_PREFIX:
                $formattedPrice = $currency . ' ' . $formattedPrice;
                break;
            case self::CURRENCY_LOCATION_SUFFIX:
                $formattedPrice = $formattedPrice . ' ' . $currency;
                break;
        }

        return $formattedPrice;
    }

    /**
     * @param float $float
     * @param string|null $locale
     *
     * @throws PriceFormatterException
     *
     * @return string
     */
    public function formatToWholeNumbers(
        $float,
        $locale = null
    ) {
        if (is_null($locale)) {
            $locale = $this->defaultLocale;
        }

        $formatter = $this->retrieveDecimalFormatter($locale);

        return $formatter->format($float);
    }

    /**
     * @param string $locale
     * @param int $digits
     *
     * @return NumberFormatter
     */
    protected function getFormatter($locale, $digits)
    {
        $formatter = $this->retrieveDecimalFormatter($locale);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $digits);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $digits);
        $formatter->setAttribute(NumberFormatter::DECIMAL_ALWAYS_SHOWN, 1);

        return $formatter;
    }

    /**
     * @param string $locale
     *
     * @return NumberFormatter
     */
    protected function retrieveDecimalFormatter($locale)
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);

        return $formatter;
    }
}
