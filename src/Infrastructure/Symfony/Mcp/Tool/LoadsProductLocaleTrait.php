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

namespace Sulu\Product\Infrastructure\Symfony\Mcp\Tool;

use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Exception\UnknownLocaleException;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * Loads a product by uuid alone and resolves one locale of it, like the admin controllers do.
 * Filtering the query by locale would throw ProductNotFoundException for a locale that has no
 * content yet, which an editor can still open and fill.
 *
 * A locale that no webspace has is rejected, because content in it can never be shown in the admin.
 *
 * Needs $this->productRepository, $this->contentManager and $this->projectLocales.
 *
 * @internal
 */
trait LoadsProductLocaleTrait
{
    /**
     * @return array{ProductInterface, array<string, mixed>|null} the normalized draft content, null when the product has no content for the locale yet
     *
     * @throws ProductNotFoundException when the uuid is unknown
     * @throws UnknownLocaleException when no webspace has the locale
     */
    private function loadProductLocale(string $uuid, string $locale): array
    {
        $this->projectLocales->assertExists($locale);

        $dimensionAttributes = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ];

        $product = $this->productRepository->getOneBy(
            ['uuid' => $uuid],
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    'dimensionAttributes' => $dimensionAttributes,
                    'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
                ],
                ProductRepositoryInterface::SELECT_PRODUCT_FAMILY => true,
            ],
        );

        try {
            $dimensionContent = $this->contentManager->resolve($product, $dimensionAttributes);
        } catch (ContentNotFoundException) {
            return [$product, null];
        }

        return [$product, $this->contentManager->normalize($dimensionContent)];
    }
}
