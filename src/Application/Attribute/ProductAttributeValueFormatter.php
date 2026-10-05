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

namespace Sulu\Product\Application\Attribute;

use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;

/**
 * A value as displayed: option name, text or number with its display format and unit, date in its
 * display format or the locale's default, boolean as true or false, so a template can test it.
 */
class ProductAttributeValueFormatter
{
    public function __construct(
        private readonly MeasurementRegistry $measurementRegistry,
    ) {
    }

    public function format(ProductAttributeValueInterface $productAttributeValue, string $locale): string|bool|null
    {
        $attribute = $productAttributeValue->getAttribute();

        return match ($attribute->getType()) {
            AttributeInterface::TYPE_OPTIONS => $productAttributeValue->getAttributeOption()?->getTranslation($locale)?->getName()
                ?? $productAttributeValue->getAttributeOptionKey(),
            AttributeInterface::TYPE_TEXT => $this->applyDisplayFormat($attribute, $productAttributeValue->getText()),
            AttributeInterface::TYPE_NUMBER => $this->applyDisplayFormat($attribute, $productAttributeValue->getNumber()),
            AttributeInterface::TYPE_DATE => $this->formatDate($attribute, $productAttributeValue->getNumber(), $locale),
            AttributeInterface::TYPE_BOOLEAN => $this->formatBoolean($productAttributeValue->getNumber()),
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

    /**
     * The display format is an ICU pattern, still rendered in the locale so month names translate;
     * without one the locale's medium date, because a bare `05.03.2024` names a different month elsewhere.
     * Rendered in UTC, because a date is stored as midnight UTC.
     */
    private function formatDate(AttributeInterface $attribute, ?float $timestamp, string $locale): ?string
    {
        if (null === $timestamp) {
            return null;
        }

        $format = $attribute->getConfig()['displayFormat'] ?? null;

        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            'UTC',
            null,
            \is_string($format) && '' !== $format ? $format : null,
        );

        return $formatter->format(new \DateTimeImmutable('@' . (int) $timestamp)) ?: null;
    }

    private function formatBoolean(?float $number): ?bool
    {
        return null === $number ? null : 0.0 !== $number;
    }
}
