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

namespace Sulu\Product\Application\Ai;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\AttributeType\AttributeTypeInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;

/** Shared read helpers for the AI tools: live dimension content lookup and attribute value formatting. */
trait ResolvesLiveProductContentTrait
{
    /**
     * @return array{0: ?ProductDimensionContentInterface, 1: ?ProductDimensionContentInterface} [localized, unlocalized]
     */
    private function findLiveDimensionContents(ProductInterface $product, string $locale): array
    {
        $localized = null;
        $unlocalized = null;

        foreach ($product->getDimensionContents() as $dimensionContent) {
            if (DimensionContentInterface::STAGE_LIVE !== $dimensionContent->getStage()
                || DimensionContentInterface::CURRENT_VERSION !== $dimensionContent->getVersion()
            ) {
                continue;
            }

            if ($locale === $dimensionContent->getLocale()) {
                $localized = $dimensionContent;
            } elseif (null === $dimensionContent->getLocale()) {
                $unlocalized = $dimensionContent;
            }
        }

        return [$localized, $unlocalized];
    }

    /**
     * @return array{code: string, title: string, productFamily: ?string, url: ?string}|null
     */
    private function toProductSummary(ProductInterface $product, string $locale): ?array
    {
        [$localized, $unlocalized] = $this->findLiveDimensionContents($product, $locale);

        if (null === $localized || null === $unlocalized) {
            return null;
        }

        $title = $localized->getTitle();
        $code = $unlocalized->getCode();

        if (null === $title || null === $code) {
            return null;
        }

        return [
            'code' => $code,
            'title' => $title,
            'productFamily' => $unlocalized->getProductFamily()?->getTranslation($locale)?->getName(),
            'url' => $localized->getRoute()?->getSlug(),
        ];
    }

    private function displayAttributeValue(ProductAttributeValueInterface $value, AttributeTypeInterface $type, string $locale): ?string
    {
        if (AttributeInterface::TYPE_OPTIONS === $type->getKey()) {
            $option = $value->getAttributeOption();

            if (null === $option) {
                return null;
            }

            return $option->getTranslation($locale)?->getName() ?? $option->getKey();
        }

        $raw = $type->readValue($value);

        if (null === $raw) {
            return null;
        }

        if (\is_float($raw)) {
            $formatted = \rtrim(\rtrim(\number_format($raw, 6, '.', ''), '0'), '.');

            return '' === $formatted ? '0' : $formatted;
        }

        if (!\is_string($raw)) {
            return null;
        }

        $formatted = \trim($raw);

        return '' === $formatted ? null : $formatted;
    }
}
