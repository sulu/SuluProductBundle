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
use Sulu\Content\Application\ResourceLoader\Loader\ResourceLoaderContentViewEnhancementInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal if you need to override this service, create a new service with based on ResourceLoaderInterface instead of extending this class
 *
 * @final
 */
class ProductResourceLoader implements ResourceLoaderContentViewEnhancementInterface, ResetInterface
{
    public const RESOURCE_LOADER_KEY = 'product';

    /**
     * Parent slugs collected by load(), keyed by parent uuid alone: the dimension content that
     * reaches resolveContentViewEnhancement() carries the shadow locale where one applies, so a
     * locale in the key would miss. One locale at a time instead, dropped when load() changes it.
     *
     * @var array<string, string|null>
     */
    private array $parentSlugs = [];

    private ?string $parentSlugsLocale = null;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private string $variantQueryParameter,
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
            [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true]
        );

        $mappedResult = [];
        foreach ($result as $product) {
            $mappedResult[$product->getId()] = $product;
        }

        $this->collectParentSlugs($mappedResult, $locale);

        return $mappedResult;
    }

    /**
     * A variant owns no route, so its URL is the parent's plus the code that selects it. Merged here
     * rather than resolved: the content resolver applies a loader enhancement after property
     * mapping, so a reference always carries a usable `url`.
     */
    public function resolveContentViewEnhancement(mixed $resource): ContentView
    {
        if (!$resource instanceof ProductDimensionContentInterface) {
            return ContentView::create([], []);
        }

        $product = $resource->getResource();
        $parent = $product->getParent();
        $code = $resource->getCode();
        $locale = $resource->getLocale();

        if (!$product->isType(ProductInterface::TYPE_VARIANT)
            || null === $parent
            || null === $code
            || '' === $code
            || null === $locale
        ) {
            return ContentView::create([], []);
        }

        $slug = $this->parentSlugs[$parent->getUuid()] ?? null;

        if (null === $slug) {
            // an unpublished parent is no link, and an empty string would read as the site root
            return ContentView::create([], []);
        }

        return ContentView::create(
            ['url' => $slug . '?' . \http_build_query([$this->variantQueryParameter => $code])],
            [],
        );
    }

    public static function getKey(): string
    {
        return self::RESOURCE_LOADER_KEY;
    }

    public function reset(): void
    {
        $this->parentSlugs = [];
        $this->parentSlugsLocale = null;
    }

    /**
     * One query for the whole batch, so a listing of variants costs the same as one.
     *
     * @param array<int|string, ProductInterface> $products
     */
    private function collectParentSlugs(array $products, string $locale): void
    {
        if ($locale !== $this->parentSlugsLocale) {
            $this->parentSlugs = [];
            $this->parentSlugsLocale = $locale;
        }

        $uuids = [];
        foreach ($products as $product) {
            $parent = $product->getParent();
            if ($product->isType(ProductInterface::TYPE_VARIANT) && null !== $parent) {
                $uuid = $parent->getUuid();
                if (!\array_key_exists($uuid, $this->parentSlugs)) {
                    $uuids[$uuid] = true;
                }
            }
        }

        if ([] === $uuids) {
            return;
        }

        $slugs = $this->productRepository->findSlugsBy([
            'uuids' => \array_keys($uuids),
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ]);

        // A parent the query did not return is remembered as null, so a second batch does not ask again.
        foreach (\array_keys($uuids) as $uuid) {
            $this->parentSlugs[$uuid] = $slugs[$uuid] ?? null;
        }
    }
}
