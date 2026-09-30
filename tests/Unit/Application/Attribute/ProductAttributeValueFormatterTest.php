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
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;

#[CoversClass(ProductAttributeValueFormatter::class)]
class ProductAttributeValueFormatterTest extends TestCase
{
    private ProductAttributeValueFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ProductAttributeValueFormatter(new MeasurementRegistry());
    }

    private function value(string $type, string $key = 'attribute'): ProductAttributeValue
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey($key);
        $attribute->setType($type);

        return new ProductAttributeValue((new Product())->createDimensionContent(), $attribute, $key);
    }

    public function testNumberWithDisplayFormatAndUnit(): void
    {
        $value = $this->value(AttributeInterface::TYPE_NUMBER);
        $value->getAttribute()->setConfig(['displayFormat' => '%value% %unit%', 'unit' => 'MILLIMETER']);
        $value->setNumber(16.0);

        $this->assertSame('16 mm', $this->formatter->format($value, 'en'));
    }

    public function testNumberWithoutDisplayFormatRendersBare(): void
    {
        $value = $this->value(AttributeInterface::TYPE_NUMBER);
        $value->setNumber(16.5);

        $this->assertSame('16.5', $this->formatter->format($value, 'en'));
    }

    public function testOptionUsesTheTranslatedNameOrFallsBackToItsKey(): void
    {
        $value = $this->value(AttributeInterface::TYPE_OPTIONS);
        $option = new AttributeOption($value->getAttribute(), 'red');
        $value->setAttributeOption($option);

        $this->assertSame('red', $this->formatter->format($value, 'en'));

        $option->addTranslation(new AttributeOptionTranslation($option, 'en', 'Red'));

        $this->assertSame('Red', $this->formatter->format($value, 'en'));
    }

    public function testDateIsFormattedForTheLocale(): void
    {
        $value = $this->value(AttributeInterface::TYPE_DATE);
        $value->setNumber((float) (new \DateTimeImmutable('2024-05-01', new \DateTimeZone('UTC')))->getTimestamp());

        $this->assertSame('May 1, 2024', $this->formatter->format($value, 'en'));
    }

    public function testDateWithoutATimestampFormatsToNull(): void
    {
        $this->assertNull($this->formatter->format($this->value(AttributeInterface::TYPE_DATE), 'en'));
    }

    public function testEmptyValueFormatsToNull(): void
    {
        $value = $this->value(AttributeInterface::TYPE_TEXT);
        $value->setText('');

        $this->assertNull($this->formatter->format($value, 'en'));
    }

    public function testUnknownTypeFormatsToNull(): void
    {
        $this->assertNull($this->formatter->format($this->value('custom_boolean'), 'en'));
    }
}
