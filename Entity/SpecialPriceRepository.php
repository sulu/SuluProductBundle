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

use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

class SpecialPriceRepository extends EntityRepository
{
    /**
     * Returns the current special prices
     *
     * @param int $limit
     * @param int $page
     *
     * @return null|Pagerfanta
     */
    public function findAllCurrent($limit = 1000, $page = 1)
    {
        try {
            $qb = $this->createQueryBuilder('specialPrice')
                ->leftJoin('specialPrice.product', 'product')
                ->leftJoin('product.status', 'productStatus')
                ->where(':now BETWEEN specialPrice.startDate AND specialPrice.endDate')
                ->andWhere('productStatus.id = :productStatus')
                ->setParameter('productStatus', Status::ACTIVE)
                ->setParameter('now', new \DateTime());

            $adapter = new DoctrineORMAdapter($qb);
            $pagerfanta = new Pagerfanta($adapter);
            $pagerfanta->setMaxPerPage($limit);
            $pagerfanta->setCurrentPage($page);

            return $pagerfanta;
        } catch (NoResultException $exc) {
            return null;
        }
    }
}
