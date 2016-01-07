<?php

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
