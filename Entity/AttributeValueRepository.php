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
use Sulu\Bundle\ProductBundle\Product\AttributeValueRepositoryInterface;

class AttributeValueRepository extends EntityRepository implements AttributeValueRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findById($id)
    {
        try {
            $queryBuilder = $this->createQueryBuilder('values')
                ->andWhere('values.id = :valuesId')
                ->setParameter('valuesId', $id);

            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdAndLocale($id, $locale)
    {
        try {
            $queryBuilder = $this->getAttributeValueQuery($locale);
            $queryBuilder->leftJoin('values.attribute', 'attribute', 'WITH', 'attribute.id = :attributeId');
            $queryBuilder->setParameter('attributeId', $id);

            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findAllByLocale($locale)
    {
        try {
            return $this->getAttributeValueQuery($locale)->getQuery()->getResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns the query for attributes
     * @param string $locale The locale to load
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getAttributeValueQuery($locale)
    {
        $queryBuilder = $this->createQueryBuilder('values')
            ->leftJoin('values.translations', 'translations', 'WITH', 'translations.locale = :locale')
            ->setParameter('locale', $locale);

        return $queryBuilder;
    }
}
