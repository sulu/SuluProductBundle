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

use Sulu\Bundle\ProductBundle\Api\TaxClass;
use Sulu\Bundle\ProductBundle\Entity\TaxClassRepository;

/**
 * Manager responsible for product statuses.
 */
class TaxClassManager
{
    /**
     * @var TaxClassRepository
     */
    private $taxClassRepository;

    public function __construct(TaxClassRepository $taxClassRepository)
    {
        $this->taxClassRepository = $taxClassRepository;
    }

    /**
     * @param $locale
     *
     * @return null|TaxClass[]
     */
    public function findAll($locale)
    {
        $taxClasses = $this->taxClassRepository->findAllByLocale($locale);

        array_walk(
            $taxClasses,
            function (&$taxClass) use ($locale) {
                $taxClass = new TaxClass($taxClass, $locale);
            }
        );

        return $taxClasses;
    }
}
