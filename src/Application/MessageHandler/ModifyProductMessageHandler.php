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
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Application\Mapper\ProductMapperInterface;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Domain\Event\ProductModifiedEvent;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * @internal This class should not be instantiated by a project.
 *           Create a ProductMapper to extend this Handler.
 */
final class ModifyProductMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        /** @var iterable<ProductMapperInterface> */
        private iterable $productMappers,
        private DomainEventCollectorInterface $domainEventCollector
    ) {
    }

    public function __invoke(ModifyProductMessage $message): ProductInterface
    {
        $identifier = $message->getIdentifier();
        $data = $message->getData();
        $locale = $message->getLocale();

        /** @var string|null $code */
        $code = $data['code'] ?? null;

        $product = $this->productRepository->getOneBy(
            [
                ...$identifier,
                'locale' => $locale,
            ],
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
                    'dimensionAttributes' => [
                        'locale' => $locale,
                        'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
                    ],
                ],
            ]
        );

        if (null !== $code && $code !== $product->getCode() && $this->productRepository->existBy(['code' => $code])) {
            throw new ProductCodeNotUniqueException($code);
        }

        foreach ($this->productMappers as $productMapper) {
            $productMapper->mapProductData($product, $data);
        }

        $this->domainEventCollector->collect(new ProductModifiedEvent($product, $locale, $data));

        return $product;
    }
}
