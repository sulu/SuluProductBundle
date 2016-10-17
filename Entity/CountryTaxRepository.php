<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\NoResultException;
use Sulu\Component\Persistence\Repository\ORM\EntityRepository;

/**
 * Entity repository for country taxes.
 */
class CountryTaxRepository extends EntityRepository
{
    /**
     * @param string $locale The locale to load
     *
     * @return CountryTax|null
     */
    public function findByLocaleAndTaxClassId($locale, $taxClassId)
    {
        try {
            $qb = $this->createQueryBuilder('countryTaxes')
                ->join('countryTaxes.taxClass', 'taxClass', 'WITH', 'taxClass.id = :taxClassId')
                ->join('countryTaxes.country', 'country', 'WITH', 'country.code = :locale')
                ->setParameter('taxClassId', $taxClassId)
                ->setParameter('locale', $locale);

            return $qb->getQuery()->getSingleResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }
}
