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

use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\FormattableAttributeTypeInterface;
use Sulu\Product\Domain\Model\AttributeInterface;

/**
 * A value as displayed: by its attribute type when that formats, else as plain text.
 */
class ProductAttributeValueFormatter
{
    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    /**
     * @param mixed $value as {@see AttributeValueConverter::normalize()} returns it
     */
    public function format(AttributeInterface $attribute, mixed $value, string $locale): string|bool|null
    {
        $type = $this->attributeTypeRegistry->has($attribute->getType())
            ? $this->attributeTypeRegistry->get($attribute->getType())
            : null;

        if ($type instanceof FormattableAttributeTypeInterface) {
            return $type->formatValue($attribute, $value, $locale);
        }

        return $this->formatPlain($value);
    }

    /**
     * The value as text, the non-empty values of several fields joined by commas.
     */
    private function formatPlain(mixed $value): ?string
    {
        $texts = [];
        foreach (\is_array($value) ? $value : [$value] as $field) {
            if ((\is_string($field) || \is_int($field) || \is_float($field)) && '' !== $field) {
                $texts[] = (string) $field;
            }
        }

        return [] === $texts ? null : \implode(', ', $texts);
    }
}
