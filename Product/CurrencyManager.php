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

use Sulu\Bundle\ProductBundle\Api\Currency;
use Sulu\Bundle\ProductBundle\Entity\CurrencyRepository;

/**
 * Manager responsible for currencies.
 */
class CurrencyManager
{
    /**
     * @var CurrencyRepository
     */
    private $currencyRepository;

    public function __construct(CurrencyRepository $repo)
    {
        $this->currencyRepository = $repo;
    }

    /**
     * Find all currencies.
     *
     * @param $locale
     *
     * @return Currency[]
     */
    public function findAll($locale)
    {
        $currencies = $this->currencyRepository->findAll();

        array_walk(
            $currencies,
            function (&$currency) use ($locale) {
                $currency = new Currency($currency, $locale);
            }
        );

        return $currencies;
    }

    /**
     * @param $locale
     * @param $code
     *
     * @return Currency
     */
    public function findByCode($locale, $code)
    {
        $currency = $this->currencyRepository->findByCode($code);
        if (!$currency) {
            return null;
        }

        return new Currency($currency, $locale);
    }

    /**
     * Finds a currency by id and locale.
     *
     * @param $id
     * @param $locale
     *
     * @return \Sulu\Bundle\ProductBundle\Api\Currency
     */
    public function findById($id, $locale)
    {
        $currency = $this->currencyRepository->findById($id);

        return new Currency($currency, $locale);
    }
}
