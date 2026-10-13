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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Product\Application\AttributeType\OptionsAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionInterface;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeOptionTranslationInterface;
use Sulu\Product\Tests\Unit\Support\FormOf;

#[CoversClass(OptionsAttributeType::class)]
class OptionsAttributeTypeTest extends TestCase
{
    use ProphecyTrait;

    public function testKey(): void
    {
        $type = new OptionsAttributeType();
        self::assertSame(AttributeInterface::TYPE_OPTIONS, $type->getKey());
    }

    public function testConfigureFieldFallsBackToOptionKeyWithoutTranslation(): void
    {
        $option = $this->prophesize(AttributeOptionInterface::class);
        $option->getKey()->willReturn('red');
        $option->getTranslation('en')->willReturn(null);

        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getOptions()->willReturn([$option->reveal()]);

        $field = new FieldMetadata('attribute_1');
        $field->setType('single_select');

        (new OptionsAttributeType())->configureForm(FormOf::fields($field), $attribute->reveal(), 'en');

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

        $field = new FieldMetadata('attribute_1');
        $field->setType('single_select');

        (new OptionsAttributeType())->configureForm(FormOf::fields($field), $attribute->reveal(), 'en');

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

    public function testConfigureFormIgnoresItemsThatAreNotFields(): void
    {
        $form = new FormMetadata();
        $form->addItem(new SectionMetadata('section'));

        (new OptionsAttributeType())->configureForm($form, new Attribute(new AttributeGroup()), 'en');

        $this->addToAssertionCount(1);
    }

    public function testFormatValueUsesTheTranslatedNameOrFallsBackToTheKey(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $option = new AttributeOption($attribute, 'red');
        $attribute->addOption($option);

        self::assertSame('red', (new OptionsAttributeType())->formatValue($attribute, 'red', 'en'));

        $option->addTranslation(new AttributeOptionTranslation($option, 'en', 'Red'));

        self::assertSame('Red', (new OptionsAttributeType())->formatValue($attribute, 'red', 'en'));
        self::assertSame('red', (new OptionsAttributeType())->formatValue($attribute, 'red', 'de'));
        self::assertSame('blue', (new OptionsAttributeType())->formatValue($attribute, 'blue', 'en'));
    }

    public function testFormatValueWithoutAnOptionKeyIsNull(): void
    {
        $attribute = new Attribute(new AttributeGroup());

        self::assertNull((new OptionsAttributeType())->formatValue($attribute, null, 'en'));
        self::assertNull((new OptionsAttributeType())->formatValue($attribute, ['red'], 'en'));
    }

    public function testHasNoDisplayFormat(): void
    {
        self::assertFalse((new OptionsAttributeType())->hasDisplayFormat());
    }
}
