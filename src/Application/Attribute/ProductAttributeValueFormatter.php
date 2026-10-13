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

    /**
     * @param mixed $value the attribute's value as the admin API has it, see {@see AttributeValueConverter::read()}
     */
    public function format(AttributeInterface $attribute, mixed $value, string $locale): string|bool|null
    {
        return match ($attribute->getType()) {
            AttributeInterface::TYPE_OPTIONS => $this->formatOption($attribute, $value, $locale),
            AttributeInterface::TYPE_MULTI_OPTIONS => $this->formatOptions($attribute, $value, $locale),
            AttributeInterface::TYPE_DATE => $this->formatDate($attribute, $value, $locale),
            AttributeInterface::TYPE_BOOLEAN => $this->formatBoolean($value),
            AttributeInterface::TYPE_RANGE => $this->formatRange($attribute, $value),
            default => $this->applyDisplayFormat($attribute, $value),
        };
    }

    /**
     * The names of the selected options, separated by commas.
     */
    private function formatOptions(AttributeInterface $attribute, mixed $optionKeys, string $locale): ?string
    {
        if (!\is_array($optionKeys)) {
            return null;
        }

        $names = \array_filter(
            \array_map(fn (mixed $optionKey): ?string => $this->formatOption($attribute, $optionKey, $locale), $optionKeys),
            static fn (?string $name): bool => null !== $name,
        );

        return [] === $names ? null : \implode(', ', $names);
    }

    private function formatOption(AttributeInterface $attribute, mixed $optionKey, string $locale): ?string
    {
        if (!\is_string($optionKey)) {
            return null;
        }

        return $attribute->getOption($optionKey)?->getTranslation($locale)?->getName() ?? $optionKey;
    }

    /**
     * A range renders as "from – to", open on one side as "≥ from" or "≤ to". Its display format may
     * also place each bound on its own with %from% and %to%, a missing one as empty.
     */
    private function formatRange(AttributeInterface $attribute, mixed $range): ?string
    {
        $from = \is_array($range) && \is_float($range['from'] ?? null) ? (string) $range['from'] : '';
        $to = \is_array($range) && \is_float($range['to'] ?? null) ? (string) $range['to'] : '';

        $value = match (true) {
            '' !== $from && '' !== $to => $from . ' – ' . $to,
            '' !== $from => '≥ ' . $from,
            '' !== $to => '≤ ' . $to,
            default => null,
        };

        return $this->applyDisplayFormat($attribute, $value, ['%from%' => $from, '%to%' => $to]);
    }

    /**
     * @param array<string, string> $placeholders further placeholders of the type, e.g. a range's bounds
     */
    private function applyDisplayFormat(AttributeInterface $attribute, mixed $value, array $placeholders = []): ?string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value) || '' === $value) {
            return null;
        }

        $config = $attribute->getConfig();
        $format = $config['displayFormat'] ?? null;

        if (!\is_string($format) || '' === $format) {
            return (string) $value;
        }

        $unitKey = $config['unit'] ?? null;
        $unit = \is_string($unitKey) ? $this->measurementRegistry->findUnit($unitKey) : null;

        $placeholders = ['%value%' => (string) $value, '%unit%' => $unit?->getSymbol() ?? ''] + $placeholders;

        return \trim(\str_replace(\array_keys($placeholders), \array_values($placeholders), $format));
    }

    /**
     * The display format is an ICU pattern, still rendered in the locale so month names translate;
     * without one the locale's medium date, because a bare `05.03.2024` names a different month elsewhere.
     * Rendered in UTC, because a date is stored as midnight UTC.
     */
    private function formatDate(AttributeInterface $attribute, mixed $date, string $locale): ?string
    {
        if (!\is_string($date)) {
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

        return $formatter->format(new \DateTimeImmutable($date, new \DateTimeZone('UTC'))) ?: null;
    }

    private function formatBoolean(mixed $value): ?bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return \is_string($value) ? AttributeValueConverter::TRUE === $value : null;
    }
}
