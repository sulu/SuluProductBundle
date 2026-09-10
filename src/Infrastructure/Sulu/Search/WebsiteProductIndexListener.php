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
 * A variant document carries values, slug and webspaces of its parent, so the parent's variants
 * are reindexed with it. The changed product itself is always part of the identifiers: the
 * provider yields no document for a product that has variants, so the engine drops the document
 * a product had before it got its first variant.
 *
 * @internal this class is internal no backwards compatibility promise is given for this class
 *           use Symfony Dependency Injection to override or create your own Listener instead
 */
final class WebsiteProductIndexListener
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
