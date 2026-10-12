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

namespace Sulu\Product\Tests\Unit\Application\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Application\Attribute\ProductAttributeValueFormatter;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;

#[CoversClass(ProductAttributeValueFormatter::class)]
class ProductAttributeValueFormatterTest extends TestCase
{
    private ProductAttributeValueFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ProductAttributeValueFormatter(new MeasurementRegistry());
    }

    private function attribute(string $type): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey('attribute');
        $attribute->setType($type);

        return $attribute;
    }

    public function testNumberWithDisplayFormatAndUnit(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_NUMBER);
        $attribute->setConfig(['displayFormat' => '%value% %unit%', 'unit' => 'MILLIMETER']);

        $this->assertSame('16 mm', $this->formatter->format($attribute, 16.0, 'en'));
    }

    public function testNumberWithoutDisplayFormatRendersBare(): void
    {
        $this->assertSame('16.5', $this->formatter->format($this->attribute(AttributeInterface::TYPE_NUMBER), 16.5, 'en'));
    }

    public function testTextWithDisplayFormat(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_TEXT);
        $attribute->setConfig(['displayFormat' => 'Size: %value%']);

        $this->assertSame('Size: XL', $this->formatter->format($attribute, 'XL', 'en'));
    }

    public function testOptionUsesTheTranslatedNameOrFallsBackToItsKey(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_OPTIONS);
        $option = new AttributeOption($attribute, 'red');
        $attribute->addOption($option);

        $this->assertSame('red', $this->formatter->format($attribute, 'red', 'en'));

        $option->addTranslation(new AttributeOptionTranslation($option, 'en', 'Red'));

        $this->assertSame('Red', $this->formatter->format($attribute, 'red', 'en'));
        $this->assertSame('red', $this->formatter->format($attribute, 'red', 'de'));
    }

    public function testUnknownOptionFallsBackToItsKey(): void
    {
        $this->assertSame('blue', $this->formatter->format($this->attribute(AttributeInterface::TYPE_OPTIONS), 'blue', 'en'));
    }

    public function testOptionWithoutAKeyFormatsToNull(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_OPTIONS);

        $this->assertNull($this->formatter->format($attribute, null, 'en'));
        $this->assertNull($this->formatter->format($attribute, ['red'], 'en'));
    }

    public function testMultiOptionsListsTheTranslatedNamesWithKeyFallbacks(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_MULTI_OPTIONS);
        $red = new AttributeOption($attribute, 'red');
        $red->addTranslation(new AttributeOptionTranslation($red, 'en', 'Red'));
        $attribute->addOption($red);
        $attribute->addOption(new AttributeOption($attribute, 'blue'));

        $this->assertSame('Red, blue', $this->formatter->format($attribute, ['red', 'blue'], 'en'));
        $this->assertSame('red, blue', $this->formatter->format($attribute, ['red', 'blue'], 'de'));
        $this->assertSame('blue, Red', $this->formatter->format($attribute, ['blue', 'red'], 'en'));
        $this->assertSame('Red', $this->formatter->format($attribute, ['red'], 'en'));
    }

    public function testMultiOptionsWithAnUnknownOptionFallsBackToItsKey(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_MULTI_OPTIONS);
        $red = new AttributeOption($attribute, 'red');
        $red->addTranslation(new AttributeOptionTranslation($red, 'en', 'Red'));
        $attribute->addOption($red);

        $this->assertSame('Red, gone', $this->formatter->format($attribute, ['red', 'gone'], 'en'));
    }

    public function testMultiOptionsKeepsAnOptionNamedZero(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_MULTI_OPTIONS);
        $attribute->addOption(new AttributeOption($attribute, '0'));

        $this->assertSame('0, 1', $this->formatter->format($attribute, ['0', '1'], 'en'));
        $this->assertSame('0', $this->formatter->format($attribute, ['0'], 'en'));
    }

    public function testMultiOptionsSkipsEntriesThatAreNoStrings(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_MULTI_OPTIONS);
        $attribute->addOption(new AttributeOption($attribute, 'red'));
        $attribute->addOption(new AttributeOption($attribute, 'blue'));

        $this->assertSame('red, blue', $this->formatter->format($attribute, ['red', 1, null, ['blue'], 'blue'], 'en'));
        $this->assertNull($this->formatter->format($attribute, [1, null, ['red'], true], 'en'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function multiOptionsWithoutOptionsProvider(): iterable
    {
        yield 'null' => [null];
        yield 'a string' => ['red'];
        yield 'a number' => [1.0];
        yield 'an empty list' => [[]];
    }

    #[DataProvider('multiOptionsWithoutOptionsProvider')]
    public function testMultiOptionsWithoutOptionsFormatsToNull(mixed $value): void
    {
        $this->assertNull($this->formatter->format($this->attribute(AttributeInterface::TYPE_MULTI_OPTIONS), $value, 'en'));
    }

    public function testDateIsFormattedForTheLocale(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_DATE);

        $this->assertSame('May 1, 2024', $this->formatter->format($attribute, '2024-05-01', 'en'));
        $this->assertSame('01.05.2024', $this->formatter->format($attribute, '2024-05-01', 'de'));
    }

    public function testDateUsesTheDisplayFormatAsIcuPattern(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_DATE);
        $attribute->setConfig(['displayFormat' => 'd MMMM yyyy']);

        $this->assertSame('1 May 2024', $this->formatter->format($attribute, '2024-05-01', 'en'));
        $this->assertSame('1 Mai 2024', $this->formatter->format($attribute, '2024-05-01', 'de'));
    }

    public function testDateWithoutAValueFormatsToNull(): void
    {
        $this->assertNull($this->formatter->format($this->attribute(AttributeInterface::TYPE_DATE), null, 'en'));
    }

    public function testEmptyValueFormatsToNull(): void
    {
        $this->assertNull($this->formatter->format($this->attribute(AttributeInterface::TYPE_TEXT), '', 'en'));
        $this->assertNull($this->formatter->format($this->attribute(AttributeInterface::TYPE_TEXT), null, 'en'));
        $this->assertNull($this->formatter->format($this->attribute(AttributeInterface::TYPE_NUMBER), null, 'en'));
    }

    public function testBooleanFormatsToABool(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_BOOLEAN);

        $this->assertTrue($this->formatter->format($attribute, 'true', 'de'));
        $this->assertFalse($this->formatter->format($attribute, 'false', 'de'));
    }

    public function testBooleanWithoutAValueFormatsToNull(): void
    {
        $this->assertNull($this->formatter->format($this->attribute(AttributeInterface::TYPE_BOOLEAN), null, 'en'));
    }

    public function testBooleanAcceptsABool(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_BOOLEAN);

        $this->assertTrue($this->formatter->format($attribute, true, 'de'));
        $this->assertFalse($this->formatter->format($attribute, false, 'de'));
    }

    public function testBooleanOfAnotherShapeFormatsToNull(): void
    {
        $this->assertNull($this->formatter->format($this->attribute(AttributeInterface::TYPE_BOOLEAN), 1, 'en'));
    }

    public function testUnknownTypeFormatsAScalarByItsShape(): void
    {
        $attribute = $this->attribute('custom_boolean');

        $this->assertSame('true', $this->formatter->format($attribute, 'true', 'en'));
        $this->assertSame('4.5', $this->formatter->format($attribute, 4.5, 'en'));
    }

    public function testUnknownTypeAppliesTheDisplayFormat(): void
    {
        $attribute = $this->attribute('custom_number');
        $attribute->setConfig(['displayFormat' => '%value% %unit%', 'unit' => 'MILLIMETER']);

        $this->assertSame('16 mm', $this->formatter->format($attribute, 16, 'en'));
    }

    public function testUnknownTypeFormatsANonScalarValueToNull(): void
    {
        $attribute = $this->attribute('custom_range');

        $this->assertNull($this->formatter->format($attribute, ['from' => 1.0, 'to' => 2.0], 'en'));
        $this->assertNull($this->formatter->format($attribute, true, 'en'));
        $this->assertNull($this->formatter->format($attribute, null, 'en'));
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function rangeProvider(): iterable
    {
        yield 'both bounds' => [['from' => -20.0, 'to' => 60.0], '-20 – 60'];
        yield 'decimals are kept' => [['from' => 1.5, 'to' => 4.25], '1.5 – 4.25'];
        yield 'integers are shown without decimals' => [['from' => 2.0, 'to' => 10.0], '2 – 10'];
        yield 'from only' => [['from' => 5.0], '≥ 5'];
        yield 'from only with a null to' => [['from' => 5.0, 'to' => null], '≥ 5'];
        yield 'to only' => [['to' => 7.5], '≤ 7.5'];
        yield 'to only with a null from' => [['from' => null, 'to' => 7.5], '≤ 7.5'];
        yield 'zero is a bound' => [['from' => 0.0, 'to' => 0.0], '0 – 0'];
        yield 'both bounds null' => [['from' => null, 'to' => null], null];
        yield 'empty array' => [[], null];
        yield 'null' => [null, null];
        yield 'a scalar' => [5.0, null];
        yield 'a string' => [['from' => '5', 'to' => '6'], null];
    }

    #[DataProvider('rangeProvider')]
    public function testRange(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, $this->formatter->format($this->attribute(AttributeInterface::TYPE_RANGE), $value, 'en'));
    }

    /**
     * @return iterable<string, array{string, mixed, string|null}>
     */
    public static function rangeDisplayFormatProvider(): iterable
    {
        yield '%value% is the rendered range' => ['%value% %unit%', ['from' => -20.0, 'to' => 60.0], '-20 – 60 mm'];
        yield '%value% of an open range' => ['%value% %unit%', ['from' => 5.0], '≥ 5 mm'];
        yield '%from% and %to%' => ['%from% to %to% %unit%', ['from' => 1.5, 'to' => 4.0], '1.5 to 4 mm'];
        yield 'bounds in the wrong order' => ['%to%/%from%', ['from' => 1.0, 'to' => 2.0], '2/1'];
        yield 'a missing to renders empty' => ['%from%-%to% %unit%', ['from' => 5.0], '5- mm'];
        yield 'a missing from renders empty' => ['up to %to%%unit%', ['to' => 9.0], 'up to 9mm'];
        yield 'nothing to render' => ['%from% - %to% %unit%', ['from' => null, 'to' => null], null];
    }

    #[DataProvider('rangeDisplayFormatProvider')]
    public function testRangeWithDisplayFormatAndUnit(string $format, mixed $value, ?string $expected): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_RANGE);
        $attribute->setConfig(['displayFormat' => $format, 'unit' => 'MILLIMETER']);

        $this->assertSame($expected, $this->formatter->format($attribute, $value, 'en'));
    }

    public function testRangeWithAnUnknownUnitRendersTheUnitEmpty(): void
    {
        $attribute = $this->attribute(AttributeInterface::TYPE_RANGE);
        $attribute->setConfig(['displayFormat' => '%from%-%to%%unit%', 'unit' => 'NOPE']);

        $this->assertSame('1-2', $this->formatter->format($attribute, ['from' => 1.0, 'to' => 2.0], 'en'));
    }
}
