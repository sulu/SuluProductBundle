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

use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ResourceLoader\Loader\ResourceLoaderInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductFamilyContentViewFactory;

/**
 * Resolves a family to the shape a product's `productFamily` has, so a template treats both alike.
 *
 * @internal if you need to override this service, create a new service with based on ResourceLoaderInterface instead of extending this class
 *
 * @final
 */
class ProductFamilyResourceLoader implements ResourceLoaderInterface
{
    public const RESOURCE_LOADER_KEY = 'product_family';

    public function __construct(
        private ProductFamilyRepositoryInterface $productFamilyRepository,
    ) {
    }

    /**
     * @param string[] $ids
     *
     * @return array<string, ContentView>
     */
    public function load(array $ids, ?string $locale, array $params = []): array
    {
        if (null === $locale || [] === $ids) {
            return [];
        }

        $mappedResult = [];
        foreach ($this->productFamilyRepository->findBy(['uuids' => $ids]) as $family) {
            $uuid = $family->getUuid();
            if (null === $uuid) {
                continue;
            }

            $mappedResult[$uuid] = ProductFamilyContentViewFactory::create($family, $locale);
        }

        return $mappedResult;
    }

    public static function getKey(): string
    {
        return self::RESOURCE_LOADER_KEY;
    }
}
