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

namespace Sulu\Product\Tests\Unit\Application\AttributeType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Application\AttributeType\JsonAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;

#[CoversClass(JsonAttributeType::class)]
class JsonAttributeTypeTest extends TestCase
{
    public function testKeyAndFormKey(): void
    {
        $type = new JsonAttributeType();
        self::assertSame(AttributeInterface::TYPE_JSON, $type->getKey());
        self::assertSame('product_attribute_json', $type->getFormKey());
    }

    public function testValueRoundTripUsesJsonColumn(): void
    {
        $type = new JsonAttributeType();
        $value = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'k');

        $type->writeValue($value, ['a' => 1]);

        self::assertSame(['a' => 1], $value->getJson());
        self::assertSame(['a' => 1], $type->readValue($value));
    }

    public function testWriteNullClearsJson(): void
    {
        $type = new JsonAttributeType();
        $value = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'k');
        $type->writeValue($value, ['x' => 2]);

        $type->writeValue($value, null);

        self::assertNull($value->getJson());
    }
}
