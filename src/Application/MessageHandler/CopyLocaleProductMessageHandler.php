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

use Sulu\Product\Application\Message\CopyLocaleProductMessage;
use Sulu\Product\Domain\Event\ProductTranslationCopiedEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Application\ContentCopier\ContentCopierInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * @internal This class should not be instantiated by a project.
 *           Create your own Message and Handler instead.
 */
final class CopyLocaleProductMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ContentCopierInterface $contentCopier,
        private DomainEventCollectorInterface $domainEventCollector
    ) {
    }

    public function __invoke(CopyLocaleProductMessage $message): ProductInterface
    {
        $product = $this->productRepository->getOneBy(
            $message->getIdentifier(),
            [
                ProductRepositoryInterface::SELECT_ARTICLE_CONTENT => [
                    'selects' => [],
                    'dimensionAttributes' => [
                        'locale' => [$message->getSourceLocale(), $message->getTargetLocale()],
                        'stage' => DimensionContentInterface::STAGE_DRAFT,
                    ],
                ],
            ]
        );

        $this->contentCopier->copy(
            $product,
            [
                'stage' => DimensionContentInterface::STAGE_DRAFT,
                'locale' => $message->getSourceLocale(),
            ],
            $product,
            [
                'stage' => DimensionContentInterface::STAGE_DRAFT,
                'locale' => $message->getTargetLocale(),
            ],
            [
                'ignoredAttributes' => ['mainWebspace', 'additionalWebspaces'],
            ]
        );

        $this->domainEventCollector->collect(new ProductTranslationCopiedEvent($product, $message->getTargetLocale(), $message->getSourceLocale(), []));

        return $product;
    }
}
