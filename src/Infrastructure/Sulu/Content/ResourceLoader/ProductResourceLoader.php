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

namespace Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader;

use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Content\Application\ResourceLoader\Loader\ResourceLoaderInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * @internal if you need to override this service, create a new service with based on ResourceLoaderInterface instead of extending this class
 *
 * @final
 */
class ProductResourceLoader implements ResourceLoaderInterface
{
    public const RESOURCE_LOADER_KEY = 'product';

    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @param string[] $ids
     */
    public function load(array $ids, ?string $locale, array $params = []): array
    {
        if (null === $locale) {
            return [];
        }

        $result = $this->productRepository->findBy(
            [
                'uuids' => $ids,
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            [],
            [ProductRepositoryInterface::GROUP_SELECT_ARTICLE_WEBSITE => true]
        );

        $mappedResult = [];
        foreach ($result as $product) {
            $mappedResult[$product->getId()] = $product;
        }

        return $mappedResult;
    }

    public static function getKey(): string
    {
        return self::RESOURCE_LOADER_KEY;
    }
}
