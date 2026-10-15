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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Application\AttributeType\MultiOptionsAttributeType;
use Sulu\Product\Application\AttributeType\OptionsAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Tests\Unit\Support\FormOf;

#[CoversClass(MultiOptionsAttributeType::class)]
class MultiOptionsAttributeTypeTest extends TestCase
{
    private function type(): MultiOptionsAttributeType
    {
        return new MultiOptionsAttributeType(new OptionsAttributeType());
    }

    public function testKey(): void
    {
        self::assertSame(AttributeInterface::TYPE_MULTI_OPTIONS, $this->type()->getKey());
    }

    public function testConfigureFieldAddsTheOptionsAsCollection(): void
    {
        $attribute = $this->attributeWithOptions();

        $field = new FieldMetadata('value');
        $field->setType('select');
        $this->type()->configureForm(FormOf::fields($field), $attribute, 'en');

        $options = $field->getOptions();
        self::assertSame(['values'], \array_keys($options));
        self::assertSame(OptionMetadata::TYPE_COLLECTION, $options['values']->getType());
        $valueOptions = $options['values']->getValue();
        self::assertIsArray($valueOptions);
        self::assertCount(2, $valueOptions);
        self::assertSame('red', $valueOptions[0]->getName());
        self::assertSame('Red', $valueOptions[0]->getTitle('en'));
        self::assertSame('blue', $valueOptions[1]->getName());
        self::assertSame('blue', $valueOptions[1]->getTitle('en'));
    }

    public function testConfigureFieldYieldsTheSameOptionsAsTheOptionsType(): void
    {
        $attribute = $this->attributeWithOptions();

        $multiOptionsField = new FieldMetadata('value');
        $this->type()->configureForm(FormOf::fields($multiOptionsField), $attribute, 'en');
        $optionsField = new FieldMetadata('value');
        (new OptionsAttributeType())->configureForm(FormOf::fields($optionsField), $attribute, 'en');

        self::assertEquals($optionsField->getOptions(), $multiOptionsField->getOptions());
    }

    /**
     * An attribute with the options "red" (translated in en) and "blue" (untranslated).
     */
    private function attributeWithOptions(): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        $red = new AttributeOption($attribute, 'red');
        $red->addTranslation(new AttributeOptionTranslation($red, 'en', 'Red'));
        $attribute->addOption($red);
        $attribute->addOption(new AttributeOption($attribute, 'blue'));

        return $attribute;
    }

    public function testFormatValueListsTheTranslatedNamesWithKeyFallbacks(): void
    {
        $attribute = $this->attributeWithOptions();

        self::assertSame('Red, blue', $this->type()->formatValue($attribute, ['red', 'blue'], 'en'));
        self::assertSame('red, blue', $this->type()->formatValue($attribute, ['red', 'blue'], 'de'));
        self::assertSame('blue, Red', $this->type()->formatValue($attribute, ['blue', 'red'], 'en'));
        self::assertSame('Red, gone', $this->type()->formatValue($attribute, ['red', 'gone'], 'en'));
    }

    public function testFormatValueKeepsAnOptionNamedZero(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->addOption(new AttributeOption($attribute, '0'));

        self::assertSame('0, 1', $this->type()->formatValue($attribute, ['0', '1'], 'en'));
        self::assertSame('0', $this->type()->formatValue($attribute, ['0'], 'en'));
    }

    public function testFormatValueSkipsEntriesThatAreNoStrings(): void
    {
        $attribute = $this->attributeWithOptions();

        self::assertSame('Red, blue', $this->type()->formatValue($attribute, ['red', 1, null, ['blue'], 'blue'], 'en'));
        self::assertNull($this->type()->formatValue($attribute, [1, null, ['red'], true], 'en'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function withoutOptionsProvider(): iterable
    {
        yield 'null' => [null];
        yield 'a string' => ['red'];
        yield 'a number' => [1.0];
        yield 'an empty list' => [[]];
    }

    #[DataProvider('withoutOptionsProvider')]
    public function testFormatValueWithoutOptionsIsNull(mixed $value): void
    {
        self::assertNull($this->type()->formatValue($this->attributeWithOptions(), $value, 'en'));
    }

    public function testHasNoDisplayFormat(): void
    {
        self::assertFalse($this->type()->hasDisplayFormat());
    }
}
