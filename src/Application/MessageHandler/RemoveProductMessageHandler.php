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
use Sulu\Content\Domain\Model\DimensionContentCollection;
use Sulu\Product\Application\Message\RemoveProductMessage;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * @internal This class should not be instantiated by a project.
 *           Create your own Message and Handler instead.
 */
final class RemoveProductMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private DomainEventCollectorInterface $domainEventCollector,
        private ?TrashManagerInterface $trashManager = null,
    ) {
    }

    public function __invoke(RemoveProductMessage $message): void
    {
        $product = $this->productRepository->getOneBy($message->getIdentifier());

        if ($product->isProductWithVariants()) {
            foreach ($this->productRepository->findBy(['parent' => $product->getUuid()]) as $variant) {
                /** @var string $variantResourceKey */
                $variantResourceKey = $variant::RESOURCE_KEY;

                // Store (not remove) — the parent's ON DELETE CASCADE removes the variant row once
                // $this->productRepository->remove($product) below is flushed.
                $this->trashManager?->store($variantResourceKey, $variant);
            }
        }

        $this->productRepository->remove($product);

        /** @var string $resourceKey */
        $resourceKey = $product::RESOURCE_KEY;
        $this->trashManager?->store($resourceKey, $product);

        $dimensionContentCollection = new DimensionContentCollection($product->getDimensionContents(), [], ProductDimensionContent::class);
        /** @var ProductDimensionContentInterface|null $localizedDimensionContent */
        $localizedDimensionContent = $dimensionContentCollection->getDimensionContent(['locale' => $message->getLocale()]);
        $unlocalizedDimensionContent = $dimensionContentCollection->getDimensionContent(['locale' => null, 'stage' => 'draft']);
        $context = $unlocalizedDimensionContent?->getAvailableLocales() ? ['locales' => $unlocalizedDimensionContent->getAvailableLocales()] : [];

        // Try to get title from the removed locale first, fallback to any available locale if null
        $title = $localizedDimensionContent?->getTitle();
        if (null === $title && $unlocalizedDimensionContent) {
            $availableLocales = $unlocalizedDimensionContent->getAvailableLocales() ?? [];
            foreach ($availableLocales as $availableLocale) {
                $fallbackDimensionContent = $dimensionContentCollection->getDimensionContent(['locale' => $availableLocale]);
                if ($fallbackDimensionContent instanceof ProductDimensionContentInterface && null !== $fallbackDimensionContent->getTitle()) {
                    $title = $fallbackDimensionContent->getTitle();
                    break;
                }
            }
        }

        $this->domainEventCollector->collect(new ProductRemovedEvent($product->getId(), $title, $context));
    }
}
