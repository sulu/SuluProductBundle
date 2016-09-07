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
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;

class SpecialPriceRepository extends EntityRepository
{
    /**
     * Returns the current special prices.
     *
     * @param int $limit
     * @param int $page
     *
     * @return null|Pagerfanta
     */
    public function findAllCurrent($limit = 1000, $page = 1)
    {
        try {
            $qb = $this->getValidSpecialPriceQuery();

            $adapter = new DoctrineORMAdapter($qb);
            $pagerfanta = new Pagerfanta($adapter);
            $pagerfanta->setMaxPerPage($limit);
            $pagerfanta->setCurrentPage($page);

            return $pagerfanta;
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns the ids of a specific amount of special prices.
     *
     * @param int $limit
     *
     * @return array|null
     */
    public function findAllCurrentIds($limit = 1000)
    {
        try {
            $queryBuilder = $this->getValidSpecialPriceQuery()
                ->select('specialPrice.id')
                ->addOrderBy('specialPrice.id', 'DESC');
            $query = $queryBuilder->getQuery()
                ->useResultCache(true, 3600);

            $ids = $query->getScalarResult();

            return array_column($ids, 'id');
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns special price querybuilder.
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function getValidSpecialPriceQuery()
    {
        $qb = $this->createQueryBuilder('specialPrice')
            ->leftJoin('specialPrice.product', 'product')
            ->leftJoin('product.status', 'productStatus')
            ->where(':now BETWEEN specialPrice.startDate AND specialPrice.endDate')
            ->andWhere('productStatus.id = :productStatus')
            ->setParameter('productStatus', Status::ACTIVE)
            ->setParameter('now', new \DateTime());

        return $qb;
    }
}
