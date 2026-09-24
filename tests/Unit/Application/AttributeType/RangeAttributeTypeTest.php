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
use Sulu\Product\Application\AttributeType\RangeAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(RangeAttributeType::class)]
class RangeAttributeTypeTest extends TestCase
{
    /**
     * @return array{from: ProductAttributeValue, to: ProductAttributeValue}
     */
    private function createRows(): array
    {
        $content = new ProductDimensionContent(new Product());
        $attribute = new Attribute(new AttributeGroup());

        return [
            'from' => new ProductAttributeValue($content, $attribute, 'temperature', valueKey: 'from'),
            'to' => new ProductAttributeValue($content, $attribute, 'temperature', valueKey: 'to'),
        ];
    }

    public function testKeyAndFormKey(): void
    {
        $type = new RangeAttributeType();
        self::assertSame(AttributeInterface::TYPE_RANGE, $type->getKey());
        self::assertSame('product_attribute_range', $type->getFormKey());
    }

    public function testStoresEachBoundInARowOfItsOwn(): void
    {
        self::assertSame(['from', 'to'], (new RangeAttributeType())->getValueKeys(new Attribute(new AttributeGroup()), ['from' => 1, 'to' => 2]));
    }

    public function testValueRoundTrip(): void
    {
        $type = new RangeAttributeType();
        $rows = $this->createRows();

        $type->writeValue($rows, ['from' => -20, 'to' => '60.5']);

        self::assertSame(-20.0, $rows['from']->getNumber());
        self::assertSame(60.5, $rows['to']->getNumber());
        self::assertSame(['from' => -20.0, 'to' => 60.5], $type->readValue($rows));
    }

    public function testWriteAcceptsEqualBounds(): void
    {
        $type = new RangeAttributeType();
        $rows = $this->createRows();

        $type->writeValue($rows, ['from' => 5, 'to' => 5]);

        self::assertSame(['from' => 5.0, 'to' => 5.0], $type->readValue($rows));
    }

    public function testReadWithoutRowsReturnsNull(): void
    {
        self::assertNull((new RangeAttributeType())->readValue([]));
    }

    #[DataProvider('provideInvalidValues')]
    public function testWriteRejectsInvalidValueWithoutChangingIt(mixed $raw): void
    {
        $type = new RangeAttributeType();
        $rows = $this->createRows();
        $type->writeValue($rows, ['from' => 1, 'to' => 2]);

        try {
            $type->writeValue($rows, $raw);
            self::fail('Expected the range to be rejected.');
        } catch (InvalidArgumentException) {
        }

        self::assertSame(['from' => 1.0, 'to' => 2.0], $type->readValue($rows));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'scalar' => [5];
        yield 'missing from' => [['to' => 2]];
        yield 'missing to' => [['from' => 1]];
        yield 'half filled' => [['from' => 1, 'to' => null]];
        yield 'non numeric from' => [['from' => 'cold', 'to' => 2]];
        yield 'non numeric to' => [['from' => 1, 'to' => 'hot']];
        yield 'from exceeds to' => [['from' => 3, 'to' => 2]];
    }

    public function testConfigureFieldAddsMinMaxStepFromConfig(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setConfig(['min' => -50, 'max' => 150, 'step' => 0.5]);

        $field = new FieldMetadata('attribute_1');
        (new RangeAttributeType())->configureField($field, $attribute, 'en');

        $options = $field->getOptions();
        self::assertSame('-50', $options['min']->getValue());
        self::assertSame('150', $options['max']->getValue());
        self::assertSame('0.5', $options['step']->getValue());
    }
}
