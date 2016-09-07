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

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

class TaxClassRepository extends EntityRepository
{
    /**
     * Returns the taxClasses with the given locale.
     *
     * @param string $locale The locale to load
     *
     * @return Status[]|null
     */
    public function findAllByLocale($locale)
    {
        try {
            $qb = $this->getTaxClassQuery($locale);

            return $qb->getQuery()->getResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns the query for tax classes.
     *
     * @param string $locale The locale to load
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getTaxClassQuery($locale)
    {
        $qb = $this->createQueryBuilder('taxClass')
            ->innerJoin(
                'taxClass.translations',
                'taxClassTranslations',
                'WITH',
                'taxClassTranslations.locale = :locale'
            )
            ->setParameter('locale', $locale);

        return $qb;
    }
}
