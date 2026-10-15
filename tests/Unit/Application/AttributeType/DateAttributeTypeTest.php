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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Product\Application\AttributeType\DateAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Tests\Unit\Support\FormOf;

#[CoversClass(DateAttributeType::class)]
class DateAttributeTypeTest extends TestCase
{
    public function testKey(): void
    {
        $type = new DateAttributeType();
        self::assertSame(AttributeInterface::TYPE_DATE, $type->getKey());
    }

    public function testConfigureFieldLeavesTheFieldAlone(): void
    {
        $field = new FieldMetadata('value');

        (new DateAttributeType())->configureForm(FormOf::fields($field), new Attribute(new AttributeGroup()), 'en');

        self::assertSame([], $field->getOptions());
    }

    public function testFormatValueRendersTheDateForTheLocale(): void
    {
        $attribute = new Attribute(new AttributeGroup());

        self::assertSame('May 1, 2024', (new DateAttributeType())->formatValue($attribute, '2024-05-01', 'en'));
        self::assertSame('01.05.2024', (new DateAttributeType())->formatValue($attribute, '2024-05-01', 'de'));
    }

    public function testFormatValueArrangesTheDisplayFormatSkeletonForTheLocale(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setConfig(['displayFormat' => 'yMMMd']);

        self::assertSame('May 1, 2024', (new DateAttributeType())->formatValue($attribute, '2024-05-01', 'en'));
        self::assertSame('1. Mai 2024', (new DateAttributeType())->formatValue($attribute, '2024-05-01', 'de'));
        self::assertSame('1 mai 2024', (new DateAttributeType())->formatValue($attribute, '2024-05-01', 'fr'));
    }

    public function testFormatValueArrangesAFieldOrderOfTheDisplayFormatForTheLocale(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setConfig(['displayFormat' => 'dd.MM.yyyy']);

        self::assertSame('05/01/2024', (new DateAttributeType())->formatValue($attribute, '2024-05-01', 'en'));
        self::assertSame('01.05.2024', (new DateAttributeType())->formatValue($attribute, '2024-05-01', 'de'));
    }

    public function testFormatValueWithAnEmptyDisplayFormatUsesTheMediumDate(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setConfig(['displayFormat' => '']);

        self::assertSame('May 1, 2024', (new DateAttributeType())->formatValue($attribute, '2024-05-01', 'en'));
    }

    public function testFormatValueWithoutADateIsNull(): void
    {
        self::assertNull((new DateAttributeType())->formatValue(new Attribute(new AttributeGroup()), null, 'en'));
    }

    public function testHasADisplayFormat(): void
    {
        self::assertTrue((new DateAttributeType())->hasDisplayFormat());
    }
}
