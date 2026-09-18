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
        $resourceId = $event->getResourceId();

        $identifiers = \array_map(
            fn (string $locale) => ProductInterface::RESOURCE_KEY . '__' . $resourceId . '__' . $locale,
            $this->getLocales($event),
        );

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
