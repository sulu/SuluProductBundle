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

use Sulu\Bundle\ProductBundle\Api\Unit;
use Sulu\Bundle\ProductBundle\Entity\UnitRepository;

/**
 * Manager responsible for units.
 */
class UnitManager
{
    /**
     * @var UnitRepository
     */
    private $unitRepository;

    public function __construct(UnitRepository $repo)
    {
        $this->unitRepository = $repo;
    }

    /**
     * Find all units.
     *
     * @param $locale
     *
     * @return Unit[]
     */
    public function findAll($locale)
    {
        $units = $this->unitRepository->findAllByLocale($locale);

        array_walk(
            $units,
            function (&$unit) use ($locale) {
                $unit = new Unit($unit, $locale);
            }
        );

        return $units;
    }

    /**
     * @param $locale
     * @param $abbrevation
     *
     * @return Unit
     */
    public function findByAbbrevation($locale, $abbrevation)
    {
        $unit = $this->unitRepository->findByAbbrevation($abbrevation, true);
        if (!$unit) {
            return null;
        }

        return new Unit($unit, $locale);
    }
}
