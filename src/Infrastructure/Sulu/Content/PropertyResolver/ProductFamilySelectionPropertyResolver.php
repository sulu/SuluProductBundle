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

namespace Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver;

use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\PropertyResolver\Resolver\PropertyResolverInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductFamilyResourceLoader;

/**
 * @internal if you need to override this service, create a new service with based on PropertyResolverInterface instead of extending this class
 *
 * @final
 */
class ProductFamilySelectionPropertyResolver implements PropertyResolverInterface
{
    /**
     * @param array{
     *     resourceLoader?: string,
     * } $params
     */
    public function resolve(mixed $data, string $locale, array $params = []): ContentView
    {
        if (
            !\is_array($data)
            || !\array_is_list($data)
        ) {
            return ContentView::create([], ['ids' => [], ...$params]);
        }

        $identifiers = [];
        foreach ($data as $identifier) {
            if (!\is_string($identifier)) {
                return ContentView::create([], ['ids' => [], ...$params]);
            }

            $identifiers[] = $identifier;
        }

        /** @var string $resourceLoaderKey */
        $resourceLoaderKey = $params['resourceLoader'] ?? ProductFamilyResourceLoader::getKey();

        return ContentView::createResolvablesWithReferences(
            ids: $identifiers,
            resourceLoaderKey: $resourceLoaderKey,
            resourceKey: ProductFamilyInterface::RESOURCE_KEY,
            view: ['ids' => $identifiers, ...$params],
        );
    }

    public static function getType(): string
    {
        return 'product_family_selection';
    }
}
