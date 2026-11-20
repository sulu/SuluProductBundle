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

use Sulu\Content\Application\ResourceLoader\Loader\ResourceLoaderInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;

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
        private bool $audienceTargetingEnabled = false,
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

        /** @var array<string, string>|null $properties */
        $properties = $params['properties'] ?? null;

        $result = $this->productRepository->findBy(
            [
                'uuids' => $ids,
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ],
            [],
            $this->getSelects($properties),
        );

        $mappedResult = [];
        foreach ($result as $product) {
            $mappedResult[$product->getId()] = $product;
        }

        return $mappedResult;
    }

    /**
     * Everything the content mergers read, so the products merge without a query each; the labels of
     * the attributes only when `product.attributes` is resolved.
     *
     * @param array<string, string>|null $properties the properties the references ask for, `null` for all
     *
     * @return array<string, mixed>
     */
    private function getSelects(?array $properties): array
    {
        $attributesRequested = null === $properties || \in_array(ProductResolver::getPrefix() . 'attributes', $properties, true);

        $selects = [
            ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true,
            ProductRepositoryInterface::SELECT_PRODUCT_ATTRIBUTES => $attributesRequested ? true : ['translations' => false],
            ProductRepositoryInterface::SELECT_PRODUCT_ASSOCIATIONS => true,
            ProductRepositoryInterface::SELECT_PRODUCT_ADDITIONAL_WEBSPACES => true,
            ProductRepositoryInterface::SELECT_PRODUCT_FAMILY_TRANSLATIONS => true,
        ];

        if ($this->audienceTargetingEnabled) {
            // merged into the content selects of the website group
            $selects[ProductRepositoryInterface::SELECT_PRODUCT_CONTENT] = [
                DimensionContentQueryEnhancer::SELECT_EXCERPT_AUDIENCE_TARGET_GROUPS => true,
            ];
        }

        return $selects;
    }

    public static function getKey(): string
    {
        return self::RESOURCE_LOADER_KEY;
    }
}
