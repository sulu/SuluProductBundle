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
use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Domain\Event\ProductModifiedEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * @internal
 */
final class ModifyProductMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ContentPersisterInterface $contentPersister,
        private DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public function __invoke(ModifyProductMessage $message): ProductInterface
    {
        $identifier = $message->getIdentifier();
        $data = $message->getData();
        $locale = $message->getLocale();

        $product = $this->productRepository->getOneBy(
            $identifier,
            [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                'selects' => [],
                'dimensionAttributes' => [
                    'locale' => $locale,
                    'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
                ],
            ]],
        );

        if (!\array_key_exists('template', $data)) {
            $data['template'] = ProductInterface::TEMPLATE_TYPE;
        }

        $this->contentPersister->persist($product, $data, [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ]);

        $this->domainEventCollector->collect(new ProductModifiedEvent($product, $locale, $data));

        return $product;
    }
}
