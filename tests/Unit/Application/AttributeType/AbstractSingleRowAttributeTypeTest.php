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
use Sulu\Product\Application\AttributeType\AbstractSingleRowAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContent;

#[CoversClass(AbstractSingleRowAttributeType::class)]
class AbstractSingleRowAttributeTypeTest extends TestCase
{
    private function type(): AbstractSingleRowAttributeType
    {
        return new class() extends AbstractSingleRowAttributeType {
            public function getKey(): string
            {
                return 'stub';
            }

            public function getFormKey(): string
            {
                return 'product_attribute_stub';
            }

            protected function readRow(ProductAttributeValueInterface $row): mixed
            {
                return $row->getText();
            }

            protected function writeRow(ProductAttributeValueInterface $row, mixed $raw): void
            {
                $row->setText(\is_string($raw) ? $raw : null);
            }
        };
    }

    private function createRow(): ProductAttributeValue
    {
        return new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'k');
    }

    public function testStoresTheValueInTheDefaultRow(): void
    {
        self::assertSame(
            [ProductAttributeValueInterface::DEFAULT_VALUE_KEY],
            $this->type()->getValueKeys(new Attribute(new AttributeGroup()), 'anything'),
        );
    }

    public function testHandsTheTypeTheDefaultRow(): void
    {
        $type = $this->type();
        $row = $this->createRow();

        $type->writeValue([ProductAttributeValueInterface::DEFAULT_VALUE_KEY => $row], 'Zink');

        self::assertSame('Zink', $row->getText());
        self::assertSame('Zink', $type->readValue([ProductAttributeValueInterface::DEFAULT_VALUE_KEY => $row]));
    }

    public function testReadsNullWithoutARow(): void
    {
        self::assertNull($this->type()->readValue([]));
    }
}
