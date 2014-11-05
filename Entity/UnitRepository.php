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
     * @param string $name
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

}
