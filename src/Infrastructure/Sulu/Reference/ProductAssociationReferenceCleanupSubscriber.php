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

namespace Sulu\Product\Infrastructure\Sulu\Reference;

use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cleans up stale reference records that point to a removed product.
 *
 * Sulu's built-in reference cleanup only removes reference records where the removed product is the
 * referrer (source). The `ProductAssociation` rows on referrer products are cascade-deleted at the
 * database level once the target product is removed, so the records pointing at the removed product
 * are left behind. They are deleted directly with a single DQL delete: `ProductRemovedEvent` is
 * dispatched from Doctrine's `postFlush`, so anything that re-enters `EntityManager::flush()` here -
 * such as re-running the reference refresher for every referrer - breaks change-set computation.
 *
 * @internal No BC promise is given for this class. Create your own event subscriber or use the
 * Symfony DependencyInjection container to override this service.
 */
class ProductAssociationReferenceCleanupSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ReferenceRepositoryInterface $referenceRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductRemovedEvent::class => 'onProductRemoved',
        ];
    }

    public function onProductRemoved(ProductRemovedEvent $event): void
    {
        $this->referenceRepository->removeBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => $event->getResourceId(),
            'referenceResourceKey' => ProductDimensionContentInterface::RESOURCE_KEY,
        ]);
    }
}
