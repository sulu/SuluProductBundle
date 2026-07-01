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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\Normalizer;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Normalizer\ProductAttributesNormalizer;

#[CoversClass(ProductAttributesNormalizer::class)]
class ProductAttributesNormalizerTest extends TestCase
{
    use ProphecyTrait;

    private ProductAttributesNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ProductAttributesNormalizer(
            new AttributeTypeRegistry([new NumberAttributeType()]),
        );
    }

    private function makeDimensionContent(): ProductDimensionContent
    {
        return new ProductDimensionContent(new Product());
    }

    public function testGetIgnoredAttributesForNonProductDimensionContent(): void
    {
        $result = $this->normalizer->getIgnoredAttributes(new \stdClass());

        $this->assertSame([], $result);
    }

    public function testGetIgnoredAttributesForProductDimensionContent(): void
    {
        $dc = $this->makeDimensionContent();

        $result = $this->normalizer->getIgnoredAttributes($dc);

        $this->assertSame(['attributes'], $result);
    }

    public function testEnhanceForNonProductDimensionContent(): void
    {
        $data = ['foo' => 'bar'];

        $result = $this->normalizer->enhance(new \stdClass(), $data);

        $this->assertSame($data, $result);
    }

    public function testEnhanceWithNoFamilyYieldsEmptyAttributes(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $dc */
        $dc = $this->prophesize(ProductDimensionContentInterface::class);
        $dc->getProductFamily()->willReturn(null);
        $dc->getAttributes()->willReturn(new ArrayCollection());

        $result = $this->normalizer->enhance($dc->reveal(), []);

        $this->assertSame([], $result['attributes']);
    }

    public function testEnhanceWithFamilyPrePopulatesNulls(): void
    {
        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(42);
        $attribute->getConfig()->willReturn([]);

        /** @var ObjectProphecy<ProductFamilyAttributeInterface> $familyAttribute */
        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $dc */
        $dc = $this->prophesize(ProductDimensionContentInterface::class);
        $dc->getProductFamily()->willReturn($family->reveal());
        $dc->getAttributes()->willReturn(new ArrayCollection());

        $result = $this->normalizer->enhance($dc->reveal(), []);

        $attributes = $result['attributes'];
        $this->assertIsArray($attributes);
        $this->assertArrayHasKey(42, $attributes);
        $this->assertNull($attributes[42]);
    }

    public function testEnhancePrePopulatesUnitKeyForMeasurementFamilyAttribute(): void
    {
        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(42);
        $attribute->getConfig()->willReturn(['measurementFamily' => 'length', 'unit' => 'meter']);

        /** @var ObjectProphecy<ProductFamilyAttributeInterface> $familyAttribute */
        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $dc */
        $dc = $this->prophesize(ProductDimensionContentInterface::class);
        $dc->getProductFamily()->willReturn($family->reveal());
        $dc->getAttributes()->willReturn(new ArrayCollection());

        $result = $this->normalizer->enhance($dc->reveal(), []);

        $this->assertIsArray($result['attributes']);
        $this->assertSame('meter', $result['attributes']['42_unit']);
    }

    public function testEnhanceWithAttributeValueSetsValue(): void
    {
        $pdc = new ProductDimensionContent(new Product());

        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);

        $attrValue = new ProductAttributeValue($pdc, $attribute->reveal(), 'attr-7');
        $attrValue->setNumber(3.14);

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([]);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $dc */
        $dc = $this->prophesize(ProductDimensionContentInterface::class);
        $dc->getProductFamily()->willReturn($family->reveal());
        $dc->getAttributes()->willReturn(new ArrayCollection([$attrValue]));

        $result = $this->normalizer->enhance($dc->reveal(), []);

        $attributes = $result['attributes'];
        $this->assertIsArray($attributes);
        $this->assertSame(3.14, $attributes[7]);
    }
}
