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
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;

#[CoversClass(ProductFamilyAttribute::class)]
class ProductFamilyAttributeTest extends TestCase
{
    public function testConstructorStoresRelationsAndDefaults(): void
    {
        $family = new ProductFamily();
        $attribute = new Attribute(new AttributeGroup());
        $fa = new ProductFamilyAttribute($family, $attribute);
        $this->assertSame($family, $fa->getFamily());
        $this->assertSame($attribute, $fa->getAttribute());
        $this->assertFalse($fa->isRequired());
        $this->assertFalse($fa->isVariant());
    }

    public function testSetRequiredIsFluent(): void
    {
        $fa = new ProductFamilyAttribute(new ProductFamily(), new Attribute(new AttributeGroup()));
        $this->assertSame($fa, $fa->setRequired(true));
        $this->assertTrue($fa->isRequired());
    }

    public function testSetVariantIsFluent(): void
    {
        $fa = new ProductFamilyAttribute(new ProductFamily(), new Attribute(new AttributeGroup()));
        $this->assertSame($fa, $fa->setVariant(true));
        $this->assertTrue($fa->isVariant());
    }

    public function testSetAttributeIsFluent(): void
    {
        $fa = new ProductFamilyAttribute(new ProductFamily(), new Attribute(new AttributeGroup()));
        $other = new Attribute(new AttributeGroup());
        $this->assertSame($fa, $fa->setAttribute($other));
        $this->assertSame($other, $fa->getAttribute());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $fa = new ProductFamilyAttribute(new ProductFamily(), new Attribute(new AttributeGroup()));
        $ref = new \ReflectionProperty(ProductFamilyAttribute::class, 'id');
        $ref->setValue($fa, 5);
        $this->assertSame(5, $fa->getId());
    }
}
