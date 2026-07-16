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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductVariantTitleBuilder;

#[CoversClass(ProductVariantTitleBuilder::class)]
class ProductVariantTitleBuilderTest extends TestCase
{
    use ProphecyTrait;

    private ProductVariantTitleBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ProductVariantTitleBuilder();
    }

    private function makeDimensionContent(): ProductDimensionContent
    {
        return new ProductDimensionContent(new Product());
    }

    private function setAttributeId(Attribute $attribute, int $id): void
    {
        $ref = new \ReflectionProperty(Attribute::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($attribute, $id);
    }

    public function testBuildWithNoFamilyReturnsEmptyString(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $content */
        $content = $this->prophesize(ProductDimensionContentInterface::class);
        $content->getProductFamily()->willReturn(null);

        $result = $this->builder->build($content->reveal(), 'en');

        $this->assertSame('', $result);
    }

    public function testBuildConcatenatesAxisValuesOrderedByPosition(): void
    {
        $colorAttribute = new Attribute(new AttributeGroup());
        $colorAttribute->setType(AttributeInterface::TYPE_OPTIONS);
        $colorAttribute->setPosition(0);
        $this->setAttributeId($colorAttribute, 1);

        $redOption = new AttributeOption($colorAttribute, 'red');
        $redOption->addTranslation(new AttributeOptionTranslation($redOption, 'en', 'Red'));
        $colorAttribute->addOption($redOption);

        $sizeAttribute = new Attribute(new AttributeGroup());
        $sizeAttribute->setType(AttributeInterface::TYPE_TEXT);
        $sizeAttribute->setPosition(1);
        $this->setAttributeId($sizeAttribute, 2);

        $family = new ProductFamily();
        $colorFamilyAttribute = new ProductFamilyAttribute($family, $colorAttribute);
        $colorFamilyAttribute->setVariant(true);
        $sizeFamilyAttribute = new ProductFamilyAttribute($family, $sizeAttribute);
        $sizeFamilyAttribute->setVariant(true);
        // Added in reverse of attribute position, to prove the builder sorts by position
        // rather than by family-attribute insertion order.
        $family->addFamilyAttribute($sizeFamilyAttribute);
        $family->addFamilyAttribute($colorFamilyAttribute);

        $dimensionContent = $this->makeDimensionContent();
        $dimensionContent->setProductFamily($family);

        // Also added out of position order, to prove ordering does not depend on
        // attribute-value insertion order either.
        $sizeValue = new ProductAttributeValue($dimensionContent, $sizeAttribute, 'attr-2');
        $sizeValue->setText('Large');
        $dimensionContent->addAttribute($sizeValue);

        $colorValue = new ProductAttributeValue($dimensionContent, $colorAttribute, 'attr-1');
        $colorValue->setAttributeOptionKey('red');
        $dimensionContent->addAttribute($colorValue);

        $result = $this->builder->build($dimensionContent, 'en');

        $this->assertSame('Red / Large', $result);
    }

    public function testBuildSkipsNonVariantAttributes(): void
    {
        $sharedAttribute = new Attribute(new AttributeGroup());
        $sharedAttribute->setType(AttributeInterface::TYPE_TEXT);
        $sharedAttribute->setPosition(0);
        $this->setAttributeId($sharedAttribute, 1);

        $family = new ProductFamily();
        $sharedFamilyAttribute = new ProductFamilyAttribute($family, $sharedAttribute);
        $sharedFamilyAttribute->setVariant(false);
        $family->addFamilyAttribute($sharedFamilyAttribute);

        $dimensionContent = $this->makeDimensionContent();
        $dimensionContent->setProductFamily($family);

        $sharedValue = new ProductAttributeValue($dimensionContent, $sharedAttribute, 'attr-1');
        $sharedValue->setText('Red');
        $dimensionContent->addAttribute($sharedValue);

        $result = $this->builder->build($dimensionContent, 'en');

        $this->assertSame('', $result);
    }

    public function testBuildSkipsAxisAttributeWithoutValue(): void
    {
        $axisAttribute = new Attribute(new AttributeGroup());
        $axisAttribute->setType(AttributeInterface::TYPE_TEXT);
        $axisAttribute->setPosition(0);
        $this->setAttributeId($axisAttribute, 1);

        $family = new ProductFamily();
        $axisFamilyAttribute = new ProductFamilyAttribute($family, $axisAttribute);
        $axisFamilyAttribute->setVariant(true);
        $family->addFamilyAttribute($axisFamilyAttribute);

        $dimensionContent = $this->makeDimensionContent();
        $dimensionContent->setProductFamily($family);

        $result = $this->builder->build($dimensionContent, 'en');

        $this->assertSame('', $result);
    }

    public function testBuildFallsBackToOptionKeyWhenOptionIsUnknown(): void
    {
        $colorAttribute = new Attribute(new AttributeGroup());
        $colorAttribute->setType(AttributeInterface::TYPE_OPTIONS);
        $colorAttribute->setPosition(0);
        $this->setAttributeId($colorAttribute, 1);
        // Deliberately no options added to $colorAttribute.

        $family = new ProductFamily();
        $colorFamilyAttribute = new ProductFamilyAttribute($family, $colorAttribute);
        $colorFamilyAttribute->setVariant(true);
        $family->addFamilyAttribute($colorFamilyAttribute);

        $dimensionContent = $this->makeDimensionContent();
        $dimensionContent->setProductFamily($family);

        $colorValue = new ProductAttributeValue($dimensionContent, $colorAttribute, 'attr-1');
        $colorValue->setAttributeOptionKey('unknown-key');
        $dimensionContent->addAttribute($colorValue);

        $result = $this->builder->build($dimensionContent, 'en');

        $this->assertSame('unknown-key', $result);
    }

    public function testBuildWithFakeFamilyInterfaceOrdersByPositionUsingProphecy(): void
    {
        $sizeAttribute = new Attribute(new AttributeGroup());
        $sizeAttribute->setType(AttributeInterface::TYPE_TEXT);
        $sizeAttribute->setPosition(5);
        $this->setAttributeId($sizeAttribute, 9);

        /** @var ObjectProphecy<ProductFamilyAttributeInterface> $familyAttribute */
        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->isVariant()->willReturn(true);
        $familyAttribute->getAttribute()->willReturn($sizeAttribute);

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        $dimensionContent = $this->makeDimensionContent();
        $dimensionContent->setProductFamily($family->reveal());

        $sizeValue = new ProductAttributeValue($dimensionContent, $sizeAttribute, 'attr-9');
        $sizeValue->setText('XL');
        $dimensionContent->addAttribute($sizeValue);

        $result = $this->builder->build($dimensionContent, 'en');

        $this->assertSame('XL', $result);
    }
}
