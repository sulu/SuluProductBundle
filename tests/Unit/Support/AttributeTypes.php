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

namespace Sulu\Product\Tests\Unit\Support;

use Sulu\Product\Application\Attribute\AttributeDisplayFormatter;
use Sulu\Product\Application\Attribute\ProductAttributeValueFormatter;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\BooleanAttributeType;
use Sulu\Product\Application\AttributeType\DateAttributeType;
use Sulu\Product\Application\AttributeType\MultiOptionsAttributeType;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Application\AttributeType\OptionsAttributeType;
use Sulu\Product\Application\AttributeType\RangeAttributeType;
use Sulu\Product\Application\AttributeType\TextAttributeType;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Tests\Unit\Fixture\LocaleKeyTranslator;

/**
 * @internal
 */
final class AttributeTypes
{
    public static function displayFormatter(): AttributeDisplayFormatter
    {
        return new AttributeDisplayFormatter(new MeasurementRegistry());
    }

    public static function number(): NumberAttributeType
    {
        return new NumberAttributeType(self::displayFormatter());
    }

    /**
     * A formatter knowing the built-in types.
     */
    public static function formatter(): ProductAttributeValueFormatter
    {
        $options = new OptionsAttributeType();

        return new ProductAttributeValueFormatter(new AttributeTypeRegistry([
            self::number(),
            new TextAttributeType(self::displayFormatter()),
            new DateAttributeType(),
            $options,
            new BooleanAttributeType(new LocaleKeyTranslator()),
            new MultiOptionsAttributeType($options),
            new RangeAttributeType(self::number(), self::displayFormatter()),
        ]));
    }
}
