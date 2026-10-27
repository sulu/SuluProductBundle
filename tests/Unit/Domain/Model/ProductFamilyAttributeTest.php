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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Value\ProductFamilyAttributeModeEnum;

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
        $this->assertSame(ProductFamilyAttributeModeEnum::SHARED, $fa->getMode());
    }

    public function testSetRequiredIsFluent(): void
    {
        $fa = new ProductFamilyAttribute(new ProductFamily(), new Attribute(new AttributeGroup()));
        $this->assertSame($fa, $fa->setRequired(true));
        $this->assertTrue($fa->isRequired());
    }

    public function testSetModeIsFluent(): void
    {
        $fa = new ProductFamilyAttribute(new ProductFamily(), new Attribute(new AttributeGroup()));
        $this->assertSame($fa, $fa->setMode(ProductFamilyAttributeModeEnum::VARIANT_SPECIFIC));
        $this->assertSame(ProductFamilyAttributeModeEnum::VARIANT_SPECIFIC, $fa->getMode());
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

    /**
     * @return iterable<string, array{0: string, 1: ProductFamilyAttributeModeEnum, 2: bool}>
     */
    public static function provideAvailability(): iterable
    {
        yield 'product holds shared' => [ProductInterface::TYPE_PRODUCT, ProductFamilyAttributeModeEnum::SHARED, true];
        yield 'product holds variant specific' => [ProductInterface::TYPE_PRODUCT, ProductFamilyAttributeModeEnum::VARIANT_SPECIFIC, true];
        yield 'product holds overridable' => [ProductInterface::TYPE_PRODUCT, ProductFamilyAttributeModeEnum::OVERRIDABLE, true];
        yield 'product with variants holds shared' => [ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, ProductFamilyAttributeModeEnum::SHARED, true];
        yield 'product with variants leaves variant specific' => [ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, ProductFamilyAttributeModeEnum::VARIANT_SPECIFIC, false];
        yield 'product with variants holds overridable' => [ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, ProductFamilyAttributeModeEnum::OVERRIDABLE, true];
        yield 'variant leaves shared' => [ProductInterface::TYPE_VARIANT, ProductFamilyAttributeModeEnum::SHARED, false];
        yield 'variant holds variant specific' => [ProductInterface::TYPE_VARIANT, ProductFamilyAttributeModeEnum::VARIANT_SPECIFIC, true];
        yield 'variant holds overridable' => [ProductInterface::TYPE_VARIANT, ProductFamilyAttributeModeEnum::OVERRIDABLE, true];
    }

    #[DataProvider('provideAvailability')]
    public function testIsAvailable(string $productType, ProductFamilyAttributeModeEnum $mode, bool $expected): void
    {
        $fa = new ProductFamilyAttribute(new ProductFamily(), new Attribute(new AttributeGroup()));
        $fa->setMode($mode);

        $this->assertSame($expected, $fa->isAvailable($productType));
    }

    /**
     * @return iterable<string, array{0: string, 1: ProductFamilyAttributeModeEnum, 2: bool}>
     */
    public static function provideRequiredFor(): iterable
    {
        yield 'product requires shared' => [ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, ProductFamilyAttributeModeEnum::SHARED, true];
        yield 'product with variants requires overridable' => [ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, ProductFamilyAttributeModeEnum::OVERRIDABLE, true];
        yield 'product with variants does not require variant specific' => [ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, ProductFamilyAttributeModeEnum::VARIANT_SPECIFIC, false];
        yield 'variant requires variant specific' => [ProductInterface::TYPE_VARIANT, ProductFamilyAttributeModeEnum::VARIANT_SPECIFIC, true];
        yield 'variant does not require overridable' => [ProductInterface::TYPE_VARIANT, ProductFamilyAttributeModeEnum::OVERRIDABLE, false];
        yield 'variant does not require shared' => [ProductInterface::TYPE_VARIANT, ProductFamilyAttributeModeEnum::SHARED, false];
        yield 'simple product requires any mode' => [ProductInterface::TYPE_PRODUCT, ProductFamilyAttributeModeEnum::VARIANT_SPECIFIC, true];
    }

    #[DataProvider('provideRequiredFor')]
    public function testIsRequiredFor(string $productType, ProductFamilyAttributeModeEnum $mode, bool $expected): void
    {
        $fa = new ProductFamilyAttribute(new ProductFamily(), new Attribute(new AttributeGroup()));
        $fa->setMode($mode)->setRequired(true);

        $this->assertSame($expected, $fa->isRequiredFor($productType));
    }

    public function testIsRequiredForIsFalseWhenNotRequired(): void
    {
        $fa = new ProductFamilyAttribute(new ProductFamily(), new Attribute(new AttributeGroup()));
        $fa->setMode(ProductFamilyAttributeModeEnum::SHARED);

        $this->assertFalse($fa->isRequiredFor(ProductInterface::TYPE_PRODUCT));
    }
}
