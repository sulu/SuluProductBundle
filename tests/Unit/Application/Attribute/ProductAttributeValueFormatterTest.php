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

    public function testUnknownTypeFormatsToNull(): void
    {
        $this->assertNull($this->formatter->format($this->attribute('custom_boolean'), 'true', 'en'));
    }
}
