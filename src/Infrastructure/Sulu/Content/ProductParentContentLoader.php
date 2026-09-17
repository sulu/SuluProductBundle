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

namespace Sulu\Product\Infrastructure\Sulu\Content;

use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Loads the parent content of a variant in the variant's locale and stage, once per request: the
 * content enhancer loads it and the resolver reads what the enhancer loaded for the same page.
 *
 * @internal
 */
class ProductParentContentLoader implements ResetInterface
{
    /**
     * @var array<string, ProductDimensionContentInterface|null>
     */
    private array $parentContents = [];

    /**
     * @var \WeakMap<ProductDimensionContentInterface, ProductDimensionContentInterface|null>
     */
    private \WeakMap $loadedFor;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ContentAggregatorInterface $contentAggregator,
    ) {
        $this->loadedFor = new \WeakMap();
    }

    /**
     * @return ProductDimensionContentInterface|null null for a product without parent or a parent without content there
     */
    public function load(ProductDimensionContentInterface $dimensionContent): ?ProductDimensionContentInterface
    {
        $parent = $dimensionContent->getResource()->getParent();
        $locale = $dimensionContent->getLocale();

        if (null === $parent || null === $locale) {
            return null;
        }

        $dimensionAttributes = ['locale' => $locale, 'stage' => $dimensionContent->getStage()];
        $key = $parent->getUuid() . '/' . $locale . '/' . $dimensionAttributes['stage'];

        if (!\array_key_exists($key, $this->parentContents)) {
            $this->parentContents[$key] = $this->loadContent($parent->getUuid(), $dimensionAttributes);
        }

        return $this->loadedFor[$dimensionContent] = $this->parentContents[$key];
    }

    /**
     * Only content that went through load() has a parent here. Resolving without the enhancers,
     * as the reference index does, keeps a variant as itself.
     */
    public function getLoaded(ProductDimensionContentInterface $dimensionContent): ?ProductDimensionContentInterface
    {
        return $this->loadedFor[$dimensionContent] ?? null;
    }

    public function reset(): void
    {
        $this->parentContents = [];
        $this->loadedFor = new \WeakMap();
    }

    /**
     * @param array{locale: string, stage: string} $dimensionAttributes
     */
    private function loadContent(string $uuid, array $dimensionAttributes): ?ProductDimensionContentInterface
    {
        $product = $this->productRepository->findOneBy(
            ['uuid' => $uuid, ...$dimensionAttributes],
            [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true],
        );

        if (null === $product) {
            return null;
        }

        try {
            return $this->contentAggregator->aggregate($product, $dimensionAttributes);
        } catch (ContentNotFoundException) {
            return null;
        }
    }
}
