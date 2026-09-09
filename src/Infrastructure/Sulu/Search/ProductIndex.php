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
 * Names of the catalogue index and its documents and dynamic fields.
 */
final class ProductIndex
{
    public const NAME = 'products';
    public const ATTRIBUTE_FIELD_PREFIX = 'attr_';
    public const OPTION_FIELD_PREFIX = 'opt_';

    private function __construct()
    {
    }

    public static function documentId(string $productId, string $locale): string
    {
        return ProductInterface::RESOURCE_KEY . '__' . $productId . '__' . $locale;
    }

    /**
     * Field holding the numeric values of a number or date attribute.
     */
    public static function attributeField(string $key): string
    {
        return self::ATTRIBUTE_FIELD_PREFIX . self::sanitize($key);
    }

    /**
     * Field holding the option keys of an options attribute.
     */
    public static function optionField(string $key): string
    {
        return self::OPTION_FIELD_PREFIX . self::sanitize($key);
    }

    /**
     * Search engines only accept word characters in field names.
     */
    private static function sanitize(string $key): string
    {
        return (string) \preg_replace('/[^A-Za-z0-9_]/', '_', $key);
    }
}
