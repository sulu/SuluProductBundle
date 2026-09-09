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

namespace Sulu\Product\Infrastructure\Sulu\Search;

use CmsIg\Seal\Reindex\ReindexConfig;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Event\ProductTranslationRemovedEvent;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A parent document carries its variants' values and a variant document its parent's,
 * so both sides are reindexed whichever one changed. That holds for a removal too: the
 * reindex provider yields nothing for a deleted row, so the engine deletes that identifier
 * while the surviving relatives are rebuilt without the gone values.
 *
 * @internal this class is internal no backwards compatibility promise is given for this class
 *           use Symfony Dependency Injection to override or create your own Listener instead
 */
final class CatalogueProductIndexListener
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function onProductChanged(ProductWorkflowTransitionAppliedEvent|ProductRemovedEvent|ProductTranslationRemovedEvent $event): void
    {
        $productIds = $event instanceof ProductRemovedEvent
            ? \array_values(\array_unique([$event->getResourceId(), ...$event->getRelatedProductIds()]))
            : $this->relatedProductIds($event->getProduct());

        $identifiers = [];
        foreach ($this->getLocales($event) as $locale) {
            foreach ($productIds as $productId) {
                $identifiers[] = ProductIndex::documentId($productId, $locale);
            }
        }

        if ([] === $identifiers) {
            return;
        }

        $this->messageBus->dispatch(
            ReindexConfig::create()
                ->withIndex(ProductIndex::NAME)
                ->withIdentifiers($identifiers),
        );
    }

    /**
     * @return string[]
     */
    private function relatedProductIds(ProductInterface $product): array
    {
        $ids = [$product->getUuid()];

        $parent = $product->getParent();
        if (null !== $parent) {
            $ids[] = $parent->getUuid();
        }

        foreach ($product->getVariants() as $variant) {
            $ids[] = $variant->getUuid();
        }

        return \array_values(\array_unique($ids));
    }

    /**
     * @return string[]
     */
    private function getLocales(ProductWorkflowTransitionAppliedEvent|ProductRemovedEvent|ProductTranslationRemovedEvent $event): array
    {
        if ($event instanceof ProductRemovedEvent) {
            return $event->getAllLocales() ?? [];
        }

        return $event->getResourceLocale() ? [$event->getResourceLocale()] : [];
    }
}
