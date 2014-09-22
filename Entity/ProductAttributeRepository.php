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
use Sulu\Bundle\ProductBundle\Product\AttributeRepositoryInterface;

class ProductAttributeRepository extends EntityRepository
{
    /**
     * Returns the productAttribute for the given Id
     * @param id
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function findById($id)
    {
        try {
            $queryBuilder = $this->createQueryBuilder('productAttribute')
                ->andWhere('productAttribute.id = :productAttributeId')
                ->setParameter('productAttributeId', $id);

            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns the productAttribute for the given attribute and product id
     * @param attributeId
     * @param productId
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

    /**
     * Returns the query for attributes
     * @param string $locale The locale to load
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getAttributeQuery($locale)
    {
        $queryBuilder = $this->createQueryBuilder('attribute')
            ->leftJoin('attribute.translations', 'translations', 'WITH', 'translations.locale = :locale')
            ->leftJoin('attribute.type', 'type')
            ->setParameter('locale', $locale);

        return $queryBuilder;
    }
}
