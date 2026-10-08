<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Infrastructure\Doctrine\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Sulu\Product\Application\Routing\VariantRouting;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Route\Domain\Model\Route;

/**
 * Drops the route of a product that does not own one: in `route` mode a product with variants, in
 * `query_parameter` mode a variant. Dropped on flush rather than in the form, so no programmatic
 * write can leave one behind.
 *
 * @internal
 */
class ProductRouteOwnerGuard
{
    public function __construct(
        private readonly VariantRouting $routing,
    ) {
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $entities = [
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
        ];

        foreach ($entities as $entity) {
            if (!$entity instanceof ProductDimensionContentInterface) {
                continue;
            }

            $route = $entity->getRoute();
            if (!$route instanceof Route) {
                continue;
            }

            if ($this->ownsRoute($entity->getResource())) {
                continue;
            }

            $entity->removeRoute();

            // Recompute, never compute: a second computeChangeSet() on a scheduled insert replaces
            // its full changeset with a one-field diff, leaving the INSERT short of bound values.
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($entity::class), $entity);

            if ($unitOfWork->isScheduledForInsert($route)) {
                $entityManager->detach($route);
            }
        }
    }

    private function ownsRoute(ProductInterface $product): bool
    {
        return match ($this->routing) {
            VariantRouting::Route => !$product->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS),
            VariantRouting::QueryParameter => null === $product->getParent(),
        };
    }
}
