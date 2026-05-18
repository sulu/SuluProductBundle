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

use Sulu\Product\Application\Message\RestoreProductVersionMessage;
use Sulu\Product\Domain\Event\ProductVersionRestoredEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Application\ContentCopier\ContentCopierInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;

/**
 * @internal This class should not be instantiated by a project.
 *           Create your own Message and Handler instead.
 */
class RestoreProductVersionMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ContentCopierInterface $contentCopier,
        private DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public function __invoke(RestoreProductVersionMessage $message): ProductInterface
    {
        $options = $message->getOptions();
        $stage = $options['stage'] ?? DimensionContentInterface::STAGE_DRAFT;

        $product = $this->productRepository->getOneBy(
            $message->getProductIdentifier(),
            [
                ProductRepositoryInterface::SELECT_ARTICLE_CONTENT => [
                    'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_WEBSITE => true],
                    'dimensionAttributes' => [
                        'locale' => $message->getLocale(),
                        'stage' => $stage,
                        'version' => [$message->getVersion(), DimensionContentInterface::CURRENT_VERSION],
                    ],
                ],
            ]
        );

        $dimensionContent = $this->contentCopier->copy(
            $product,
            [
                'stage' => $stage,
                'locale' => $message->getLocale(),
                'version' => $message->getVersion(),
            ],
            $product,
            [
                'stage' => $stage,
                'locale' => $message->getLocale(),
                'version' => DimensionContentInterface::CURRENT_VERSION,
            ],
            [
                'ignoredAttributes' => ['url'],
            ]
        );

        $this->domainEventCollector->collect(new ProductVersionRestoredEvent($product, $message->getLocale(), $message->getVersion()));

        return $dimensionContent->getResource();
    }
}
