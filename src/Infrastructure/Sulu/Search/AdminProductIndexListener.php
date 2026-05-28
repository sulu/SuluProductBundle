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
use Sulu\Product\Domain\Event\ProductCreatedEvent;
use Sulu\Product\Domain\Event\ProductModifiedEvent;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Event\ProductRestoredEvent;
use Sulu\Product\Domain\Event\ProductTranslationAddedEvent;
use Sulu\Product\Domain\Event\ProductTranslationCopiedEvent;
use Sulu\Product\Domain\Event\ProductTranslationRemovedEvent;
use Sulu\Product\Domain\Event\ProductTranslationRestoredEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal this class is internal no backwards compatibility promise is given for this class
 *           use Symfony Dependency Injection to override or create your own Listener instead
 */
final class AdminProductIndexListener
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function onProductChanged(ProductCreatedEvent|ProductModifiedEvent|ProductRemovedEvent|ProductRestoredEvent|ProductTranslationRestoredEvent|ProductTranslationAddedEvent|ProductTranslationRemovedEvent|ProductTranslationCopiedEvent $event): void
    {
        $locale = $event->getResourceLocale();
        $identifiers = [];

        if ($event instanceof ProductRemovedEvent || $event instanceof ProductRestoredEvent) {
            $locales = $event->getAllLocales();

            if (!$locales) {
                return;
            }

            foreach ($locales as $locale) {
                $identifiers[] = ProductInterface::RESOURCE_KEY . '__' . $event->getResourceId() . '__' . $locale;
            }
        } elseif ($locale) {
            $identifiers[] = ProductInterface::RESOURCE_KEY . '__' . $event->getResourceId() . '__' . $locale;
        }

        if (!$identifiers) {
            return;
        }

        $this->messageBus->dispatch(
            ReindexConfig::create()
                ->withIndex('admin')
                ->withIdentifiers($identifiers),
        );
    }
}
