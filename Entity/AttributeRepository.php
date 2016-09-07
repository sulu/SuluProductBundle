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
use Sulu\Bundle\ProductBundle\Product\AttributeRepositoryInterface;

class AttributeRepository extends EntityRepository implements AttributeRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function findById($id)
    {
        try {
            $queryBuilder = $this->createQueryBuilder('attribute')
                ->andWhere('attribute.id = :attributeId')
                ->setParameter('attributeId', $id);

            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByIdAndLocale($id, $locale)
    {
        try {
            $queryBuilder = $this->getAttributeQuery($locale);
            $queryBuilder->andWhere('attribute.id = :attributeId');
            $queryBuilder->setParameter('attributeId', $id);

            return $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findAllByLocale($locale)
    {
        try {
            return $this->getAttributeQuery($locale)->getQuery()->getResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findAllByLocaleAndType($locale, $type)
    {
        try {
            $queryBuilder = $this->getAttributeQuery($locale);
            $queryBuilder->andWhere('type.id = :type');
            $queryBuilder->setParameter('type', $type);

            return $queryBuilder->getQuery()->getResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns the query for attributes.
     *
     * @param string $locale The locale to load
     *
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
