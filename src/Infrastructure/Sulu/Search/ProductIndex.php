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

namespace Sulu\Product\Infrastructure\Sulu\Search;

use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Names of the shared website index, of the product object field inside it and of its documents.
 */
final class ProductIndex
{
    public const NAME = 'website';
    public const FIELD = 'product';
    public const TEXT_VALUES_FIELD = 'attributes_text_values';
    public const NUMERIC_VALUES_FIELD = 'attributes_numeric_values';

    private function __construct()
    {
    }

    public static function documentId(string $productId, string $locale): string
    {
        return ProductInterface::RESOURCE_KEY . '__' . $productId . '__' . $locale;
    }

    /**
     * Filter path of the option keys and text values of every attribute.
     */
    public static function textValuesPath(): string
    {
        return self::FIELD . '.' . self::TEXT_VALUES_FIELD;
    }

    /**
     * One entry of that field: the attribute an option key or text value belongs to is part of
     * the value, so text attributes need no field of their own.
     */
    public static function textValue(string $attributeKey, string $value): string
    {
        return self::sanitize($attributeKey) . ':' . $value;
    }

    /**
     * Filter path of a number or date attribute's values.
     */
    public static function numericValuePath(string $attributeKey): string
    {
        return self::FIELD . '.' . self::NUMERIC_VALUES_FIELD . '.' . self::numericField($attributeKey);
    }

    /**
     * Field of a number or date attribute inside the numeric object field.
     */
    public static function numericField(string $attributeKey): string
    {
        return self::sanitize($attributeKey);
    }

    /**
     * Search engines only accept word characters in field names, and a value of the text field
     * must stay parseable, so the attribute key is reduced to them in both.
     */
    private static function sanitize(string $key): string
    {
        return (string) \preg_replace('/[^A-Za-z0-9_]/', '_', $key);
    }
}
