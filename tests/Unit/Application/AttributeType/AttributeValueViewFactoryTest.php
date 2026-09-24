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
use Sulu\Product\Application\AttributeType\AbstractAttributeType;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\AttributeValueView;
use Sulu\Product\Application\AttributeType\AttributeValueViewFactory;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContent;

#[CoversClass(AttributeValueViewFactory::class)]
#[CoversClass(AttributeValueView::class)]
class AttributeValueViewFactoryTest extends TestCase
{
    public function testReadsEachAttributeFromAllOfItsRows(): void
    {
        $content = new ProductDimensionContent(new Product());
        $weight = $this->createAttribute('weight', AttributeInterface::TYPE_NUMBER);
        $size = $this->createAttribute('size', 'parts');

        $rows = [
            $this->createRow($content, $size, 'width', 20.0),
            $this->createRow($content, $weight, ProductAttributeValueInterface::DEFAULT_VALUE_KEY, 1.5),
            $this->createRow($content, $size, 'height', 30.0),
        ];

        $views = $this->createFactory()->createMap($rows);

        self::assertSame(['size', 'weight'], \array_keys($views));
        self::assertSame($size, $views['size']->getAttribute());
        self::assertSame('size', $views['size']->getKey());
        self::assertSame(['width' => 20.0, 'height' => 30.0], $views['size']->getValue());
        self::assertSame(1.5, $views['weight']->getValue());
    }

    public function testSkipsAnAttributeWhoseTypeIsNotRegistered(): void
    {
        $content = new ProductDimensionContent(new Product());
        $row = $this->createRow($content, $this->createAttribute('legacy', 'removed_type'), ProductAttributeValueInterface::DEFAULT_VALUE_KEY, 1.0);

        self::assertSame([], $this->createFactory()->createMap([$row]));
    }

    private function createFactory(): AttributeValueViewFactory
    {
        $partsType = new class() extends AbstractAttributeType {
            public function getKey(): string
            {
                return 'parts';
            }

            public function getFormKey(): string
            {
                return 'product_attribute_parts';
            }

            public function readValue(array $rows): mixed
            {
                return \array_map(static fn (ProductAttributeValueInterface $row): ?float => $row->getNumber(), $rows);
            }

            public function writeValue(array $rows, mixed $raw): void
            {
            }
        };

        return new AttributeValueViewFactory(new AttributeTypeRegistry([new NumberAttributeType(), $partsType]));
    }

    private function createAttribute(string $key, string $type): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey($key);
        $attribute->setType($type);

        return $attribute;
    }

    private function createRow(ProductDimensionContent $content, Attribute $attribute, string $valueKey, float $number): ProductAttributeValue
    {
        $row = new ProductAttributeValue($content, $attribute, $attribute->getKey(), valueKey: $valueKey);
        $row->setNumber($number);

        return $row;
    }
}
