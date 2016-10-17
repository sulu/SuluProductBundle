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
 * Entity repository for currencies.
 */
class CurrencyRepository extends EntityRepository
{
    /**
     * Find a currency by it's id.
     *
     * @param mixed $id
     *
    * @return mixed|null
    */
    public function findById($id)
    {
        try {
            $qb = $this->createQueryBuilder('currency')
                ->andWhere('currency.id = :currencyId')
                ->setParameter('currencyId', $id);

            return $qb->getQuery()->getSingleResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Find a currency by it's code.
     *
     * @param string $code
     *
     * @return mixed|null
     */
    public function findByCode($code)
    {
        try {
            $qb = $this->createQueryBuilder('currency')
                ->andWhere('currency.code = :currencyCode')
                ->setParameter('currencyCode', $code);

            return $qb->getQuery()->getSingleResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }
}
