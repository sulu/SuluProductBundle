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

namespace Sulu\Product\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;

#[CoversClass(ProductAttributeValue::class)]
class ProductAttributeValueTest extends TestCase
{
    public function testConstructorAssignsReferencesAndDefaults(): void
    {
        $pdc = new ProductDimensionContent(new Product());
        $attribute = new Attribute(new AttributeGroup());
        $productAttribute = new ProductAttributeValue($pdc, $attribute, 'color');

        $this->assertSame($pdc, $productAttribute->getProductDimensionContent());
        $this->assertSame($attribute, $productAttribute->getAttribute());
        $this->assertSame('color', $productAttribute->getAttributeKey());

        $this->assertNull($productAttribute->getAttributeOptionKey());
        $this->assertNull($productAttribute->getNumber());
        $this->assertNull($productAttribute->getText());
        $this->assertNull($productAttribute->getJson());
        $this->assertNull($productAttribute->getAttributeOption());
        $this->assertNull($productAttribute->getValue());
    }

    public function testSetAttributeOptionKeyIsFluentAndStores(): void
    {
        $productAttribute = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'color');

        $this->assertSame($productAttribute, $productAttribute->setAttributeOptionKey('red'));
        $this->assertSame('red', $productAttribute->getAttributeOptionKey());

        $productAttribute->setAttributeOptionKey(null);
        $this->assertNull($productAttribute->getAttributeOptionKey());
    }

    public function testSetNumberIsFluentAndStores(): void
    {
        $productAttribute = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'weight');

        $this->assertSame($productAttribute, $productAttribute->setNumber(42.5));
        $this->assertSame(42.5, $productAttribute->getNumber());

        $productAttribute->setNumber(null);
        $this->assertNull($productAttribute->getNumber());
    }

    public function testSetTextIsFluentAndStores(): void
    {
        $productAttribute = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'description');

        $this->assertSame($productAttribute, $productAttribute->setText('Hello'));
        $this->assertSame('Hello', $productAttribute->getText());

        $productAttribute->setText(null);
        $this->assertNull($productAttribute->getText());
    }

    public function testSetJsonIsFluentAndStores(): void
    {
        $productAttribute = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'meta');
        $data = ['foo' => 'bar', 'count' => 3];

        $this->assertSame($productAttribute, $productAttribute->setJson($data));
        $this->assertSame($data, $productAttribute->getJson());

        $productAttribute->setJson(null);
        $this->assertNull($productAttribute->getJson());
    }

    public function testSetAttributeOptionIsFluentAndStores(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $productAttribute = new ProductAttributeValue(new ProductDimensionContent(new Product()), $attribute, 'color');
        $option = new AttributeOption($attribute, 'red');

        $this->assertSame($productAttribute, $productAttribute->setAttributeOption($option));
        $this->assertSame($option, $productAttribute->getAttributeOption());

        $productAttribute->setAttributeOption(null);
        $this->assertNull($productAttribute->getAttributeOption());
    }

    public function testGetValuePrefersAttributeOptionKey(): void
    {
        $productAttribute = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'color');
        $productAttribute->setAttributeOptionKey('red');
        $productAttribute->setNumber(1.0);
        $productAttribute->setText('text');
        $productAttribute->setJson(['x' => 1]);

        $this->assertSame('red', $productAttribute->getValue());
    }

    public function testGetValueFallsBackToNumber(): void
    {
        $productAttribute = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'weight');
        $productAttribute->setNumber(2.5);
        $productAttribute->setText('text');
        $productAttribute->setJson(['x' => 1]);

        $this->assertSame(2.5, $productAttribute->getValue());
    }

    public function testGetValueFallsBackToText(): void
    {
        $productAttribute = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'description');
        $productAttribute->setText('Hello');
        $productAttribute->setJson(['x' => 1]);

        $this->assertSame('Hello', $productAttribute->getValue());
    }

    public function testGetValueFallsBackToJson(): void
    {
        $productAttribute = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'meta');
        $productAttribute->setJson(['x' => 1]);

        $this->assertSame(['x' => 1], $productAttribute->getValue());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $model = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'color');
        $ref = new \ReflectionProperty(ProductAttributeValue::class, 'id');
        $ref->setValue($model, 42);
        $this->assertSame(42, $model->getId());
    }

    public function testSetProductFamilyAttributeIsNullableAndFluent(): void
    {
        $value = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'color');
        $this->assertNull($value->getProductFamilyAttribute());

        $familyAttribute = new ProductFamilyAttribute(new ProductFamily(), new Attribute(new AttributeGroup()));
        $this->assertSame($value, $value->setProductFamilyAttribute($familyAttribute));
        $this->assertSame($familyAttribute, $value->getProductFamilyAttribute());

        $value->setProductFamilyAttribute(null);
        $this->assertNull($value->getProductFamilyAttribute());
    }
}
