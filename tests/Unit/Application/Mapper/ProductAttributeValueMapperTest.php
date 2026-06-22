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

namespace Sulu\Product\Tests\Unit\Application\Mapper;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Application\Mapper\ProductAttributeValueMapper;
use Sulu\Product\Domain\Exception\RequiredProductAttributeMissingException;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;

#[CoversClass(ProductAttributeValueMapper::class)]
class ProductAttributeValueMapperTest extends TestCase
{
    use ProphecyTrait;

    private function mapper(): ProductAttributeValueMapper
    {
        return new ProductAttributeValueMapper(
            new AttributeTypeRegistry([new NumberAttributeType()]),
        );
    }

    /**
     * @param array<int, array{required: bool}> $attributesById
     *
     * @return ObjectProphecy<ProductInterface>
     */
    private function productWithFamily(array $attributesById): ObjectProphecy
    {
        $familyAttributes = [];
        foreach ($attributesById as $id => $config) {
            /** @var ObjectProphecy<AttributeInterface> $attribute */
            $attribute = $this->prophesize(AttributeInterface::class);
            $attribute->getId()->willReturn($id);
            $attribute->getKey()->willReturn('attr-' . $id);
            $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);

            /** @var ObjectProphecy<ProductFamilyAttributeInterface> $familyAttribute */
            $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
            $familyAttribute->getAttribute()->willReturn($attribute->reveal());
            $familyAttribute->isRequired()->willReturn($config['required']);
            $familyAttributes[] = $familyAttribute->reveal();
        }

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn($familyAttributes);

        /** @var ObjectProphecy<ProductInterface> $product */
        $product = $this->prophesize(ProductInterface::class);
        $product->getProductFamily()->willReturn($family->reveal());

        return $product;
    }

    public function testAbsentAttributesKeyIsNoOp(): void
    {
        /** @var ObjectProphecy<ProductInterface> $product */
        $product = $this->prophesize(ProductInterface::class);
        $product->getProductFamily()->shouldNotBeCalled();

        $this->mapper()->mapProductData($product->reveal(), ['locale' => 'en']);

        $this->addToAssertionCount(1);
    }

    public function testCreatesNewValue(): void
    {
        $product = $this->productWithFamily([7 => ['required' => false]]);
        $product->getAttributes()->willReturn(new ArrayCollection());
        $product->addAttribute(Argument::that(
            static fn (ProductAttributeValueInterface $v): bool => 42.0 === $v->getNumber()
        ))->shouldBeCalled()->willReturn($product->reveal());

        $this->mapper()->mapProductData($product->reveal(), ['attributes' => ['attr-7' => 42.0]]);
    }

    public function testRequiredMissingThrows(): void
    {
        $product = $this->productWithFamily([7 => ['required' => true]]);
        $product->getAttributes()->willReturn(new ArrayCollection());
        $product->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(RequiredProductAttributeMissingException::class);
        $this->expectExceptionMessage('attr-7');

        $this->mapper()->mapProductData($product->reveal(), ['attributes' => ['attr-7' => null]]);
    }

    public function testUpdatesExistingValueInPlace(): void
    {
        $productFamily = new ProductFamily();
        $concreteProduct = new Product($productFamily);

        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getKey()->willReturn('attr-7');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);

        $existingValue = new ProductAttributeValue($concreteProduct, $attribute->reveal(), 'attr-7');
        $existingValue->setNumber(1.0);

        /** @var ObjectProphecy<ProductFamilyAttributeInterface> $familyAttribute */
        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        /** @var ObjectProphecy<ProductInterface> $product */
        $product = $this->prophesize(ProductInterface::class);
        $product->getProductFamily()->willReturn($family->reveal());
        $product->getAttributes()->willReturn(new ArrayCollection([$existingValue]));
        // addAttribute must NOT be called (we update in place, not duplicate)
        $product->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->mapper()->mapProductData($product->reveal(), ['attributes' => ['attr-7' => 99.0]]);

        $this->assertSame(99.0, $existingValue->getNumber());
    }

    public function testRemoveOnEmpty(): void
    {
        $productFamily = new ProductFamily();
        $concreteProduct = new Product($productFamily);

        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(7);
        $attribute->getKey()->willReturn('attr-7');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);

        $existingValue = new ProductAttributeValue($concreteProduct, $attribute->reveal(), 'attr-7');
        $existingValue->setNumber(5.0);

        /** @var ObjectProphecy<ProductFamilyAttributeInterface> $familyAttribute */
        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        /** @var ObjectProphecy<ProductInterface> $product */
        $product = $this->prophesize(ProductInterface::class);
        $product->getProductFamily()->willReturn($family->reveal());
        $product->getAttributes()->willReturn(new ArrayCollection([$existingValue]));
        $product->removeAttribute($existingValue)->shouldBeCalled()->willReturn($product->reveal());

        $this->mapper()->mapProductData($product->reveal(), ['attributes' => ['attr-7' => null]]);
    }

    public function testSkipUnenrolledAttributeId(): void
    {
        $product = $this->productWithFamily([7 => ['required' => false]]);
        $product->getAttributes()->willReturn(new ArrayCollection());
        // attribute 99 is not in the family — neither add nor remove must be called
        $product->addAttribute(Argument::cetera())->shouldNotBeCalled();
        $product->removeAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->mapper()->mapProductData($product->reveal(), ['attributes' => ['attr-99' => 42.0]]);
    }

    public function testRequiredWithValuePasses(): void
    {
        $product = $this->productWithFamily([7 => ['required' => true]]);
        $product->getAttributes()->willReturn(new ArrayCollection());
        $product->addAttribute(Argument::that(
            static fn (ProductAttributeValueInterface $v): bool => 10.0 === $v->getNumber()
        ))->shouldBeCalled()->willReturn($product->reveal());

        // Must not throw
        $this->mapper()->mapProductData($product->reveal(), ['attributes' => ['attr-7' => 10.0]]);

        $this->addToAssertionCount(1);
    }
}
