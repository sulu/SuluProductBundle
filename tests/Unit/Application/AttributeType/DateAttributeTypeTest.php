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
use Sulu\Product\Application\AttributeType\DateAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;

#[CoversClass(DateAttributeType::class)]
class DateAttributeTypeTest extends TestCase
{
    public function testKeyAndFormKey(): void
    {
        $type = new DateAttributeType();
        self::assertSame(AttributeInterface::TYPE_DATE, $type->getKey());
        self::assertSame('product_attribute_date', $type->getFormKey());
    }

    public function testValueRoundTripUsesDateColumn(): void
    {
        $type = new DateAttributeType();
        $value = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'k');

        $type->writeValue($value, '2026-07-24');

        $date = $value->getDate();
        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2026-07-24', $date->format('Y-m-d'));
        self::assertSame('00:00:00', $date->format('H:i:s'));
        self::assertSame('2026-07-24', $type->readValue($value));
    }

    public function testWriteNullClearsDate(): void
    {
        $type = new DateAttributeType();
        $value = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'k');
        $type->writeValue($value, '2026-07-24');

        $type->writeValue($value, null);

        self::assertNull($value->getDate());
        self::assertNull($type->readValue($value));
    }

    public function testWriteEmptyStringClearsDate(): void
    {
        $type = new DateAttributeType();
        $value = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'k');
        $type->writeValue($value, '2026-07-24');

        $type->writeValue($value, '');

        self::assertNull($value->getDate());
    }

    public function testWriteInvalidFormatThrows(): void
    {
        $type = new DateAttributeType();
        $value = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'k');

        $this->expectException(\InvalidArgumentException::class);

        $type->writeValue($value, 'not-a-date');
    }

    public function testReadValueReturnsNullWhenNoDate(): void
    {
        $type = new DateAttributeType();
        $value = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'k');

        self::assertNull($type->readValue($value));
    }
}
