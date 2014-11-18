<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

class UnitRepository extends EntityRepository
{
    /**
     * Find a unit by it's abbrevation
     *
     * @param $abbrevation
     * @return mixed|null
     */
    public function findByAbbrevation($abbrevation)
    {
        try {
            $qb = $this->createQueryBuilder('unit')
                ->select('partial unit.{id}')
                ->join('unit.mappings', 'mappings', 'WITH', 'mappings.name = :abbrevation')
                ->setParameter('abbrevation', $abbrevation);

            return $qb->getQuery()->getSingleScalarResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns the taxClasses with the given locale
     *
     * @param string $locale The locale to load
     * @return Status[]|null
     */
    public function findAllByLocale($locale)
    {
        try {
            $qb = $this->getUnitQuery($locale);

            return $qb->getQuery()->getResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns the query for tax classes
     *
     * @param string $locale The locale to load
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getUnitQuery($locale)
    {
        $qb = $this->createQueryBuilder('unit')
            ->innerJoin(
                'unit.translations',
                'unitTranslations',
                'WITH',
                'unitTranslations.locale = :locale'
            )
            ->setParameter('locale', $locale);

        return $qb;
    }

}
