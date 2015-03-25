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

class SpecialPriceRepository extends EntityRepository
{
    /**
     * Returns the current special prices
     *
     * @return SpecialPrice[]|null
     */
    public function findAllCurrent()
    {
        try {
            $qb = $this->createQueryBuilder('specialPrice')
                ->add('where', ':now BETWEEN specialPrice.start AND specialPrice.end')
                ->setParameter('now', new \DateTime());

            return $qb->getQuery()->getResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }
}
