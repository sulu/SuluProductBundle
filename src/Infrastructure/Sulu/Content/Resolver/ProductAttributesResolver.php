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

namespace Sulu\Product\Infrastructure\Sulu\Content\Resolver;

use Sulu\Content\Application\ContentResolver\Resolver\ResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;

/**
 * Exposes the product's attribute values as a flat map keyed by attribute key, each carrying its
 * attribute group. Grouping for display is the `sulu_product_attribute_groups` Twig filter's job.
 *
 * A value that formats to nothing is dropped.
 *
 * Nothing is emitted when the content is itself being resolved as a reference — a product
 * selection on some other page — because attributes are only read on the product's own page, and
 * formatting them per referenced product multiplies the queries on every listing.
 *
 * @internal
 */
class ProductAttributesResolver implements ResolverInterface
{
    public function __construct(private readonly MeasurementRegistry $measurementRegistry)
    {
    }

    public function resolve(DimensionContentInterface $dimensionContent, ?array $properties = null): ?ContentView
    {
        if (!$dimensionContent instanceof ProductDimensionContentInterface || null !== $properties) {
            return null;
        }

        // merged content always carries a locale
        /** @var string $locale */
        $locale = $dimensionContent->getLocale();

        return ContentView::create($this->flatten($dimensionContent, $locale), []);
    }

    /**
     * @return array<string, array{key: string, label: string, type: string, value: mixed, formattedValue: string, position: int, group: array{key: string, label: string}}>
     */
    private function flatten(ProductDimensionContentInterface $dimensionContent, string $locale): array
    {
        $attributes = [];

        foreach ($dimensionContent->getAttributes() as $value) {
            $attribute = $value->getAttribute();
            $formatted = $this->formatValue($value, $attribute, $locale);

            if (null === $formatted || '' === $formatted) {
                continue;
            }

            $group = $attribute->getGroup();
            $groupId = (string) $group->getId();

            $attributes[$attribute->getKey()] = [
                'key' => $attribute->getKey(),
                'label' => $attribute->getTranslation($locale)?->getName() ?? $value->getAttributeKey(),
                'type' => $attribute->getType(),
                'value' => $value->getValue(),
                'formattedValue' => $formatted,
                'position' => $attribute->getPosition(),
                'group' => [
                    'key' => $groupId,
                    'label' => $group->getTranslation($locale)?->getName() ?? $groupId,
                ],
            ];
        }

        return $attributes;
    }

    private function formatValue(
        ProductAttributeValueInterface $value,
        AttributeInterface $attribute,
        string $locale,
    ): ?string {
        return match ($attribute->getType()) {
            AttributeInterface::TYPE_OPTIONS => $value->getAttributeOption()?->getTranslation($locale)?->getName()
                ?? $value->getAttributeOptionKey(),
            AttributeInterface::TYPE_TEXT => $this->applyDisplayFormat($attribute, $value->getText()),
            AttributeInterface::TYPE_NUMBER => $this->applyDisplayFormat($attribute, $value->getNumber()),
            AttributeInterface::TYPE_DATE => $this->formatDate($value->getNumber(), $locale),
            default => null,
        };
    }

    /** An editor can set a unit and a display format per attribute; without them the value renders bare. */
    private function applyDisplayFormat(AttributeInterface $attribute, string|float|null $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $config = $attribute->getConfig();
        $format = $config['displayFormat'] ?? null;

        if (!\is_string($format) || '' === $format) {
            return (string) $value;
        }

        $unitKey = $config['unit'] ?? null;
        $unit = \is_string($unitKey) ? $this->measurementRegistry->findUnit($unitKey) : null;

        return \trim(\str_replace(['%value%', '%unit%'], [(string) $value, $unit?->getSymbol() ?? ''], $format));
    }

    /** Formatted per locale, because a bare `05.03.2024` names a different month elsewhere. */
    private function formatDate(?float $timestamp, string $locale): ?string
    {
        if (null === $timestamp) {
            return null;
        }

        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return $formatter->format(new \DateTimeImmutable('@' . (int) $timestamp)) ?: null;
    }
}
