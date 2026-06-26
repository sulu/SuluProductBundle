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
use Sulu\Product\Application\AttributeType\AbstractAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductFamily;

#[CoversClass(AbstractAttributeType::class)]
class AbstractAttributeTypeTest extends TestCase
{
    private function type(): AbstractAttributeType
    {
        return new class() extends AbstractAttributeType {
            public function getKey(): string
            {
                return 'stub';
            }

            public function getFormKey(): string
            {
                return 'product_attribute_stub';
            }
        };
    }

    public function testWriteAndReadValueUseJsonColumnByDefault(): void
    {
        $value = new ProductAttributeValue(new Product(new ProductFamily()), new Attribute(new AttributeGroup()), 'k');

        $this->type()->writeValue($value, ['a' => 1]);

        self::assertSame(['a' => 1], $value->getJson());
        self::assertSame(['a' => 1], $this->type()->readValue($value));
    }

    public function testConfigureFieldIsNoOp(): void
    {
        $field = new FieldMetadata('attributes/1');
        $field->setType('text_line');

        $this->type()->configureField($field, new Attribute(new AttributeGroup()), 'en');

        self::assertSame('text_line', $field->getType());
        self::assertSame([], $field->getOptions());
    }
}
