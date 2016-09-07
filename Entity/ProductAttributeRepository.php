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

class ProductAttributeRepository extends EntityRepository
{
    /**
     * Returns the productAttribute for the given attribute and product id.
     *
     * @param attributeId
     * @param productId
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function findByAttributeIdAndProductId($attributeId, $productId)
    {
        try {
            $queryBuilder = $this->createQueryBuilder('productAttribute')
                ->leftJoin('productAttribute.attribute', 'attribute')
                ->leftJoin('productAttribute.product', 'product')
                ->andWhere('attribute.id = :attributeId')
                ->andWhere('product.id = :productId')
                ->setParameter('attributeId', $attributeId)
                ->setParameter('productId', $productId);

            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }
}
