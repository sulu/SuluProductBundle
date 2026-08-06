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
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cleans up reference records left behind when a product is removed as an association target.
 *
 * Sulu's built-in cleanup only handles removed referrers, and `ProductAssociation` rows cascade-delete
 * in the database, so target-side references survive. They are removed here with a direct DQL delete,
 * because `ProductRemovedEvent` fires from Doctrine's `postFlush` and re-entering
 * `EntityManager::flush()` there breaks change-set computation.
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
            'referenceResourceKey' => ProductInterface::RESOURCE_KEY,
        ]);
    }
}
