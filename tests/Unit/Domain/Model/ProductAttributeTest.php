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
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttribute;

#[CoversClass(ProductAttribute::class)]
class ProductAttributeTest extends TestCase
{
    public function testConstructorAssignsReferencesAndDefaults(): void
    {
        $product = new Product();
        $attribute = new Attribute();
        $productAttribute = new ProductAttribute($product, $attribute, 'color');

        $this->assertSame($product, $productAttribute->getProduct());
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
        $productAttribute = new ProductAttribute(new Product(), new Attribute(), 'color');

        $this->assertSame($productAttribute, $productAttribute->setAttributeOptionKey('red'));
        $this->assertSame('red', $productAttribute->getAttributeOptionKey());

        $productAttribute->setAttributeOptionKey(null);
        $this->assertNull($productAttribute->getAttributeOptionKey());
    }

    public function testSetNumberIsFluentAndStores(): void
    {
        $productAttribute = new ProductAttribute(new Product(), new Attribute(), 'weight');

        $this->assertSame($productAttribute, $productAttribute->setNumber(42.5));
        $this->assertSame(42.5, $productAttribute->getNumber());

        $productAttribute->setNumber(null);
        $this->assertNull($productAttribute->getNumber());
    }

    public function testSetTextIsFluentAndStores(): void
    {
        $productAttribute = new ProductAttribute(new Product(), new Attribute(), 'description');

        $this->assertSame($productAttribute, $productAttribute->setText('Hello'));
        $this->assertSame('Hello', $productAttribute->getText());

        $productAttribute->setText(null);
        $this->assertNull($productAttribute->getText());
    }

    public function testSetJsonIsFluentAndStores(): void
    {
        $productAttribute = new ProductAttribute(new Product(), new Attribute(), 'meta');
        $data = ['foo' => 'bar', 'count' => 3];

        $this->assertSame($productAttribute, $productAttribute->setJson($data));
        $this->assertSame($data, $productAttribute->getJson());

        $productAttribute->setJson(null);
        $this->assertNull($productAttribute->getJson());
    }

    public function testSetAttributeOptionIsFluentAndStores(): void
    {
        $attribute = new Attribute();
        $productAttribute = new ProductAttribute(new Product(), $attribute, 'color');
        $option = new AttributeOption($attribute, 'red');

        $this->assertSame($productAttribute, $productAttribute->setAttributeOption($option));
        $this->assertSame($option, $productAttribute->getAttributeOption());

        $productAttribute->setAttributeOption(null);
        $this->assertNull($productAttribute->getAttributeOption());
    }

    public function testGetValuePrefersAttributeOptionKey(): void
    {
        $productAttribute = new ProductAttribute(new Product(), new Attribute(), 'color');
        $productAttribute->setAttributeOptionKey('red');
        $productAttribute->setNumber(1.0);
        $productAttribute->setText('text');
        $productAttribute->setJson(['x' => 1]);

        $this->assertSame('red', $productAttribute->getValue());
    }

    public function testGetValueFallsBackToNumber(): void
    {
        $productAttribute = new ProductAttribute(new Product(), new Attribute(), 'weight');
        $productAttribute->setNumber(2.5);
        $productAttribute->setText('text');
        $productAttribute->setJson(['x' => 1]);

        $this->assertSame(2.5, $productAttribute->getValue());
    }

    public function testGetValueFallsBackToText(): void
    {
        $productAttribute = new ProductAttribute(new Product(), new Attribute(), 'description');
        $productAttribute->setText('Hello');
        $productAttribute->setJson(['x' => 1]);

        $this->assertSame('Hello', $productAttribute->getValue());
    }

    public function testGetValueFallsBackToJson(): void
    {
        $productAttribute = new ProductAttribute(new Product(), new Attribute(), 'meta');
        $productAttribute->setJson(['x' => 1]);

        $this->assertSame(['x' => 1], $productAttribute->getValue());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $model = new ProductAttribute(new Product(), new Attribute(), 'color');
        $ref = new \ReflectionProperty(ProductAttribute::class, 'id');
        $ref->setValue($model, 42);
        $this->assertSame(42, $model->getId());
    }
}
