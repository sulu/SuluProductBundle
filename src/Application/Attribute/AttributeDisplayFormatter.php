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
 * Places a value in the attribute's display format, e.g. `%value% %unit%`.
 */
class AttributeDisplayFormatter
{
    public function __construct(
        private readonly MeasurementRegistry $measurementRegistry,
    ) {
    }

    /**
     * @param array<string, string> $placeholders further placeholders of the type, e.g. a range's bounds
     *
     * @return string|null the value itself without a display format, null for an empty value
     */
    public function format(AttributeInterface $attribute, mixed $value, array $placeholders = []): ?string
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
}
