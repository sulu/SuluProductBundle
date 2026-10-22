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

namespace Sulu\Product\Application\MessageHandler;

use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Message\ModifyProductComponentsMessage;
use Sulu\Product\Domain\Event\ProductModifiedEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductComponentsDataMapper;

/**
 * Writes the components straight to the unlocalized draft content. The content persister would also run
 * the workflow data mapper, which sends a published product back to draft on every save.
 *
 * @internal this class should not be instantiated by a project
 */
final class ModifyProductComponentsMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductComponentsDataMapper $componentsDataMapper,
        private DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public function __invoke(ModifyProductComponentsMessage $message): ProductInterface
    {
        $locale = $message->getLocale();

        $product = $this->productRepository->getOneBy(
            $message->getIdentifier(),
            [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                'selects' => [],
                'dimensionAttributes' => [
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
            ]],
        );

        $unlocalized = null;
        foreach ($product->getDimensionContents() as $dimensionContent) {
            if (null === $dimensionContent->getLocale()
                && DimensionContentInterface::STAGE_DRAFT === $dimensionContent->getStage()
                && DimensionContentInterface::CURRENT_VERSION === $dimensionContent->getVersion()
            ) {
                $unlocalized = $dimensionContent;
            }
        }

        if (null === $unlocalized) {
            throw new \LogicException(\sprintf('Product "%s" has no unlocalized draft content.', $product->getUuid()));
        }

        $data = ['components' => $message->getComponents()];
        $this->componentsDataMapper->map($unlocalized, $unlocalized, $data);

        $this->domainEventCollector->collect(new ProductModifiedEvent($product, $locale, $data));

        return $product;
    }
}
