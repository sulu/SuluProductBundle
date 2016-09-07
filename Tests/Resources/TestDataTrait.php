<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Tests\Resources;

trait TestDataTrait
{
    /**
     * Clones an entity and persists it.
     *
     * @param Entity $entity
     *
     * @return Entity
     */
    protected function cloneEntity($entity)
    {
        $clonedEntity = clone $entity;
        $this->entityManager->persist($clonedEntity);

        return $clonedEntity;
    }
}
