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
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Application\AttributeType\OptionsAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOptionInterface;
use Sulu\Product\Domain\Model\AttributeOptionTranslationInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;

#[CoversClass(OptionsAttributeType::class)]
class OptionsAttributeTypeTest extends TestCase
{
    use ProphecyTrait;

    public function testKeyAndFormKey(): void
    {
        $type = new OptionsAttributeType();
        self::assertSame(AttributeInterface::TYPE_OPTIONS, $type->getKey());
        self::assertSame('product_attribute_options', $type->getFormKey());
    }

    public function testValueRoundTripUsesOptionKeyColumn(): void
    {
        $type = new OptionsAttributeType();
        $value = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'k');

        $type->writeValue($value, 'red');

        self::assertSame('red', $value->getAttributeOptionKey());
        self::assertSame('red', $type->readValue($value));
    }

    public function testWriteEmptyValueClearsOptionKey(): void
    {
        $type = new OptionsAttributeType();
        $value = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'k');
        $type->writeValue($value, 'red');

        $type->writeValue($value, null);
        self::assertNull($value->getAttributeOptionKey());

        $type->writeValue($value, 'green');
        $type->writeValue($value, '');
        self::assertNull($value->getAttributeOptionKey());
    }

    public function testConfigureFieldFallsBackToOptionKeyWithoutTranslation(): void
    {
        $option = $this->prophesize(AttributeOptionInterface::class);
        $option->getKey()->willReturn('red');
        $option->getTranslation('en')->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getOptions()->willReturn([$option->reveal()]);

        $field = new FieldMetadata('attributes/1');
        $field->setType('single_select');

        (new OptionsAttributeType())->configureField($field, $attribute->reveal(), 'en');

        $valueOptions = $field->getOptions()['values']->getValue();
        self::assertIsArray($valueOptions);
        self::assertSame('red', $valueOptions[0]->getTitle('en'));
    }

    public function testConfigureFieldAddsOptionsAsCollection(): void
    {
        $translation = $this->prophesize(AttributeOptionTranslationInterface::class);
        $translation->getName()->willReturn('Red');
        $option = $this->prophesize(AttributeOptionInterface::class);
        $option->getKey()->willReturn('red');
        $option->getTranslation('en')->willReturn($translation->reveal());

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getOptions()->willReturn([$option->reveal()]);

        $field = new FieldMetadata('attributes/1');
        $field->setType('single_select');

        (new OptionsAttributeType())->configureField($field, $attribute->reveal(), 'en');

        $options = $field->getOptions();
        self::assertArrayHasKey('values', $options);
        $values = $options['values'];
        self::assertSame(OptionMetadata::TYPE_COLLECTION, $values->getType());
        $valueOptions = $values->getValue();
        self::assertIsArray($valueOptions);
        self::assertCount(1, $valueOptions);
        self::assertSame('red', $valueOptions[0]->getName());
        self::assertSame('Red', $valueOptions[0]->getTitle('en'));
    }
}
