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

class AttributeRepository extends EntityRepository implements AttributeRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findById($id)
    {
        try {
            $qb = $this->createQueryBuilder('attribute')
                ->andWhere('atribute.id = :attributeId')
                ->setParameter('attributeId', $id);

            return $qb->getQuery()->getSingleResult();
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
            $qb = $this->getAttributeQuery($locale);
            $qb->andWhere('attribute.id = :attributeId');
            $qb->setParameter('attributeId', $id);

            return $qb->getQuery()->getSingleResult();
        } catch (NoResultException $exc) {
            return null;
        }
    }

    /**
     * Returns all attributes for the given locale
     * @param string $locale The locale of the attribute to load
     * @return AttributeInterface[]
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
     * Returns the query for attributes
     * @param string $locale The locale to load
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getProductQuery($locale)
    {
        $qb = $this->createQueryBuilder('attribute')
            ->leftJoin('attribute.translations', 'translations', 'WITH', 'translations.locale = :locale')
            ->setParameter('locale', $locale);

        return $qb;
    }
}
