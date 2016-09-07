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

class UnitRepository extends EntityRepository
{
    /**
     * Find a unit by it's abbrevation.
     *
     * @param $abbrevation
     * @param bool $returnAsEntity
     *
     * @return mixed|null
     */
    public function findByAbbrevation($abbrevation, $returnAsEntity = false)
    {
        try {
            $qb = $this->createQueryBuilder('unit')
                ->select('partial unit.{id}')
                ->join('unit.mappings', 'mappings', 'WITH', 'mappings.name = :abbrevation')
                ->setParameter('abbrevation', $abbrevation);

            if ($returnAsEntity) {
                return $qb->getQuery()->getSingleResult();
            }

            return $qb->getQuery()->getSingleScalarResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns the units with the given locale.
     *
     * @param string $locale The locale to load
     *
     * @return Unit[]|null
     */
    public function findAllByLocale($locale)
    {
        try {
            $qb = $this->getUnitQuery($locale)
                ->orderBy('unitTranslations.name', 'ASC');

            return $qb->getQuery()->getResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns the query for units.
     *
     * @param string $locale The locale to load
     *
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
