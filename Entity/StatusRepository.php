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
 * Entity repository for statuses.
 */
class StatusRepository extends EntityRepository
{
    /**
     * Returns the statuses with the given locale.
     *
     * @param string $locale The locale to load
     *
     * @return Status[]|null
     */
    public function findAllByLocale($locale)
    {
        try {
            $qb = $this->getStatusQuery($locale);

            return $qb->getQuery()->getResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns the query for products.
     *
     * @param string $locale The locale to load
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getStatusQuery($locale)
    {
        $qb = $this->createQueryBuilder('status')
            ->innerJoin('status.translations', 'statusTranslations', 'WITH', 'statusTranslations.locale = :locale')
            ->setParameter('locale', $locale);

        return $qb;
    }
}
