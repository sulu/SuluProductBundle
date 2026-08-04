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
        $productAttributeValue = new ProductAttributeValue($pdc, $attribute, 'color');

        $this->assertSame($pdc, $productAttributeValue->getProductDimensionContent());
        $this->assertSame($attribute, $productAttributeValue->getAttribute());
        $this->assertSame('color', $productAttributeValue->getAttributeKey());

        $this->assertNull($productAttributeValue->getAttributeOptionKey());
        $this->assertNull($productAttributeValue->getNumber());
        $this->assertNull($productAttributeValue->getText());
        $this->assertNull($productAttributeValue->getAttributeOption());
        $this->assertNull($productAttributeValue->getValue());
    }

    public function testSetAttributeOptionKeyIsFluentAndStores(): void
    {
        $productAttributeValue = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'color');

        $this->assertSame($productAttributeValue, $productAttributeValue->setAttributeOptionKey('red'));
        $this->assertSame('red', $productAttributeValue->getAttributeOptionKey());

        $productAttributeValue->setAttributeOptionKey(null);
        $this->assertNull($productAttributeValue->getAttributeOptionKey());
    }

    public function testSetNumberIsFluentAndStores(): void
    {
        $productAttributeValue = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'weight');

        $this->assertSame($productAttributeValue, $productAttributeValue->setNumber(42.5));
        $this->assertSame(42.5, $productAttributeValue->getNumber());

        $productAttributeValue->setNumber(null);
        $this->assertNull($productAttributeValue->getNumber());
    }

    public function testSetTextIsFluentAndStores(): void
    {
        $productAttributeValue = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'description');

        $this->assertSame($productAttributeValue, $productAttributeValue->setText('Hello'));
        $this->assertSame('Hello', $productAttributeValue->getText());

        $productAttributeValue->setText(null);
        $this->assertNull($productAttributeValue->getText());
    }

    public function testSetAttributeOptionIsFluentAndStores(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $productAttributeValue = new ProductAttributeValue(new ProductDimensionContent(new Product()), $attribute, 'color');
        $option = new AttributeOption($attribute, 'red');

        $this->assertSame($productAttributeValue, $productAttributeValue->setAttributeOption($option));
        $this->assertSame($option, $productAttributeValue->getAttributeOption());

        $productAttributeValue->setAttributeOption(null);
        $this->assertNull($productAttributeValue->getAttributeOption());
    }

    public function testGetValuePrefersAttributeOptionKey(): void
    {
        $productAttributeValue = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'color');
        $productAttributeValue->setAttributeOptionKey('red');
        $productAttributeValue->setNumber(1.0);
        $productAttributeValue->setText('text');

        $this->assertSame('red', $productAttributeValue->getValue());
    }

    public function testGetValueFallsBackToNumber(): void
    {
        $productAttributeValue = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'weight');
        $productAttributeValue->setNumber(2.5);
        $productAttributeValue->setText('text');

        $this->assertSame(2.5, $productAttributeValue->getValue());
    }

    public function testGetValueFallsBackToText(): void
    {
        $productAttributeValue = new ProductAttributeValue(new ProductDimensionContent(new Product()), new Attribute(new AttributeGroup()), 'description');
        $productAttributeValue->setText('Hello');

        $this->assertSame('Hello', $productAttributeValue->getValue());
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
