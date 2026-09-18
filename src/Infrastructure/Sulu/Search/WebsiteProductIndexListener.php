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
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal this class is internal no backwards compatibility promise is given for this class
 *           use Symfony Dependency Injection to override or create your own Listener instead
 */
final class WebsiteProductIndexListener
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function onProductChanged(ProductWorkflowTransitionAppliedEvent|ProductRemovedEvent|ProductTranslationRemovedEvent $event): void
    {
        $identifiers = [];

        foreach ($this->getResourceIds($event) as $resourceId) {
            foreach ($this->getLocales($event) as $locale) {
                $identifiers[] = ProductInterface::RESOURCE_KEY . '__' . $resourceId . '__' . $locale;
            }
        }

        if ([] === $identifiers) {
            return;
        }

        $this->messageBus->dispatch(
            ReindexConfig::create()
                ->withIndex('website')
                ->withIdentifiers($identifiers),
        );
    }

    /**
     * A variant renders its product's content, so it is reindexed with its product.
     *
     * @return string[]
     */
    private function getResourceIds(ProductWorkflowTransitionAppliedEvent|ProductRemovedEvent|ProductTranslationRemovedEvent $event): array
    {
        $resourceIds = [$event->getResourceId()];

        if ($event instanceof ProductRemovedEvent) {
            return $resourceIds;
        }

        foreach ($this->productRepository->findBy(['parent' => $event->getResourceId()]) as $variant) {
            $resourceIds[] = $variant->getUuid();
        }

        return $resourceIds;
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
