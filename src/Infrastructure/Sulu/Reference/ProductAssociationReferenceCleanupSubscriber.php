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

use Doctrine\ORM\EntityManagerInterface;
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
 * database level once the target product is removed, so they can no longer be used to find those
 * referrers. This subscriber instead reads the persisted reference records - which still point at the
 * removed product - to find the referrers, then re-runs the reference refresher for each of them so
 * their reference sets are recomputed from their current content, without the removed product.
 *
 * @internal No BC promise is given for this class. Create your own event subscriber or use the
 * Symfony DependencyInjection container to override this service.
 */
class ProductAssociationReferenceCleanupSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ReferenceRepositoryInterface $referenceRepository,
        private readonly ProductReferenceRefresher $referenceRefresher,
        private readonly EntityManagerInterface $entityManager,
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
        $removedProductId = $event->getResourceId();

        /**
         * @var iterable<array{referenceResourceId: string, referenceLocale: string, referenceContext: string}> $referrers
         */
        $referrers = $this->referenceRepository->findFlatBy(
            filters: [
                'resourceKey' => ProductInterface::RESOURCE_KEY,
                'resourceId' => $removedProductId,
                'referenceResourceKey' => ProductDimensionContentInterface::RESOURCE_KEY,
            ],
            fields: ['referenceResourceId', 'referenceLocale', 'referenceContext'],
            distinct: true,
        );

        $referrersToRefresh = [];
        foreach ($referrers as $referrer) {
            if ($removedProductId === $referrer['referenceResourceId']) {
                continue;
            }

            $referrersToRefresh[] = $referrer;
        }

        if ([] === $referrersToRefresh) {
            return;
        }

        // refresh() re-enters flush() during the removal's postFlush; the identity map still holds
        // entities orphaned by the DB-cascaded dimension-content deletes, which would crash
        // change-set computation. clear() resets the whole unit of work - safe only because product
        // removal runs in isolation (no sibling ProductRemovedEvent listener retains managed
        // entities). Do not reuse this listener in a flush cycle shared with other entity work.
        $this->entityManager->clear();

        foreach ($referrersToRefresh as $referrer) {
            $this->refreshReferrer($referrer['referenceResourceId'], $referrer['referenceLocale'], $referrer['referenceContext']);
        }

        $this->referenceRepository->flush();
    }

    private function refreshReferrer(string $resourceId, string $locale, string $stage): void
    {
        $refreshedDimensionContents = $this->referenceRefresher->refresh([
            'resourceId' => $resourceId,
            'resourceKey' => ProductDimensionContentInterface::RESOURCE_KEY,
            'locale' => $locale,
            'stage' => $stage,
        ]);

        // Drain the generator to force execution of the refresh and reference persistence.
        foreach ($refreshedDimensionContents as $ignored) {
        }
    }
}
