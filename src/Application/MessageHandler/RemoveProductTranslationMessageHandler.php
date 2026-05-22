<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Application\MessageHandler;

use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Product\Application\Message\RemoveProductTranslationMessage;
use Sulu\Product\Domain\Event\ProductTranslationRemovedEvent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * @internal This class should not be instantiated by a project.
 *           Create your own Message and Handler instead.
 */
final class RemoveProductTranslationMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private DomainEventCollectorInterface $domainEventCollector,
        private ?TrashManagerInterface $trashManager = null,
    ) {
    }

    public function __invoke(RemoveProductTranslationMessage $message): ProductInterface
    {
        $product = $this->productRepository->getOneBy($message->getIdentifier());
        $locale = $message->getLocale();

        /** @var string $resourceKey */
        $resourceKey = $product::RESOURCE_KEY;
        $this->trashManager?->store($resourceKey, $product, ['locale' => $locale]);

        $dimensionContents = $product->getDimensionContents();

        foreach ($dimensionContents as $dimensionContent) {
            if ($dimensionContent->getLocale() === $locale) {
                $product->removeDimensionContent($dimensionContent);
                $this->productRepository->removeDimensionContent($dimensionContent);
                continue;
            }

            if ($dimensionContent->getGhostLocale() === $locale) {
                $this->handleGhostLocaleRemoval($dimensionContent, $product, $locale);
                continue;
            }

            if (null === $dimensionContent->getLocale()) {
                $dimensionContent->removeAvailableLocale($locale);
            }
        }

        $this->domainEventCollector->collect(new ProductTranslationRemovedEvent(
            $product,
            $locale
        ));

        return $product;
    }

    private function handleGhostLocaleRemoval(
        ProductDimensionContentInterface $dimensionContent,
        ProductInterface $product,
        string $locale
    ): void {
        $availableLocales = $dimensionContent->getAvailableLocales();
        $availableLocales = \array_values(\array_diff($availableLocales ?? [], [$locale]));

        if (empty($availableLocales)) {
            $product->removeDimensionContent($dimensionContent);
            $this->productRepository->removeDimensionContent($dimensionContent);

            return;
        }

        $dimensionContent->setGhostLocale($availableLocales[0]);
        $dimensionContent->removeAvailableLocale($locale);
    }
}
