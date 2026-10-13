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
use Sulu\Product\Application\AttributeType\TextAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Tests\Unit\Support\AttributeTypes;
use Sulu\Product\Tests\Unit\Support\FormOf;

#[CoversClass(TextAttributeType::class)]
class TextAttributeTypeTest extends TestCase
{
    public function testKey(): void
    {
        $type = new TextAttributeType(AttributeTypes::displayFormatter());
        self::assertSame(AttributeInterface::TYPE_TEXT, $type->getKey());
    }

    public function testConfigureFieldLeavesTheFieldAlone(): void
    {
        $field = new FieldMetadata('value');

        (new TextAttributeType(AttributeTypes::displayFormatter()))->configureForm(FormOf::fields($field), new Attribute(new AttributeGroup()), 'en');

        self::assertSame([], $field->getOptions());
    }

    public function testFormatValuePlacesTheTextInTheDisplayFormat(): void
    {
        $type = new TextAttributeType(AttributeTypes::displayFormatter());
        $attribute = new Attribute(new AttributeGroup());

        self::assertSame('XL', $type->formatValue($attribute, 'XL', 'en'));
        self::assertNull($type->formatValue($attribute, '', 'en'));
        self::assertNull($type->formatValue($attribute, null, 'en'));

        $attribute->setConfig(['displayFormat' => 'Size: %value%']);

        self::assertSame('Size: XL', $type->formatValue($attribute, 'XL', 'en'));
    }

    public function testHasADisplayFormat(): void
    {
        self::assertTrue((new TextAttributeType(AttributeTypes::displayFormatter()))->hasDisplayFormat());
    }
}
