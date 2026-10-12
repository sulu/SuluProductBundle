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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\DataMapper;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Exception\RequiredProductAttributeMissingException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductAttributesDataMapper;
use Sulu\Product\Tests\Unit\Support\ProductAttributeFragmentLoader;

#[CoversClass(ProductAttributesDataMapper::class)]
class ProductAttributesDataMapperTest extends TestCase
{
    use ProphecyTrait;

    private const UUID = '0198c3e2-0000-7000-8000-000000000001';

    private ProductAttributesDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ProductAttributesDataMapper(
            ProductAttributeFragmentLoader::converter([
                'range' => ['from' => 'number', 'to' => 'number'],
                'multi_options' => ['value' => 'multi_select'],
            ]),
        );
    }

    public function testEarlyReturnWhenUnlocalizedNotProductDimensionContent(): void
    {
        $other = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($other->reveal(), $other->reveal(), ['attributes' => [self::UUID => 5.0]]);

        $this->addToAssertionCount(1);
    }

    public function testEarlyReturnWhenLocalizedNotProductDimensionContent(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $unloc */
        $unloc = $this->prophesize(ProductDimensionContentInterface::class);
        $locOther = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($unloc->reveal(), $locOther->reveal(), ['attributes' => [self::UUID => 5.0]]);

        $this->addToAssertionCount(1);
    }

    public function testNoOpWhenAttributesKeyAbsent(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $unloc */
        $unloc = $this->prophesize(ProductDimensionContentInterface::class);
        $unloc->getResource()->shouldNotBeCalled();
        /** @var ObjectProphecy<ProductDimensionContentInterface> $loc */
        $loc = $this->prophesize(ProductDimensionContentInterface::class);

        $this->mapper->map($unloc->reveal(), $loc->reveal(), ['locale' => 'en', 'template' => 'product']);

        $this->addToAssertionCount(1);
    }

    public function testNoOpWhenProductFamilyIsNull(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $unloc */
        $unloc = $this->prophesize(ProductDimensionContentInterface::class);
        $unloc->getProductFamily()->willReturn(null);
        /** @var ObjectProphecy<ProductDimensionContentInterface> $loc */
        $loc = $this->prophesize(ProductDimensionContentInterface::class);

        $this->mapper->map($unloc->reveal(), $loc->reveal(), ['attributes' => [self::UUID => 5.0]]);

        $unloc->getAttributes()->shouldNotHaveBeenCalled();
        $this->addToAssertionCount(1);
    }

    public function testUnknownAttributeUuidIsSkipped(): void
    {
        $fixture = $this->makeProductFixture('attr-known', false);

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => ['attr-unknown' => 5]]);

        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotHaveBeenCalled();
        $fixture['loc_prophecy']->addAttribute(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->assertCount(0, $fixture['unloc']->getAttributes());
        $this->assertCount(0, $fixture['loc']->getAttributes());
    }

    public function testNumericAttributeKeyIsSkipped(): void
    {
        $fixture = $this->makeProductFixture('attr-known', false);

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [0 => 5]]);

        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotHaveBeenCalled();
        $fixture['loc_prophecy']->addAttribute(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->assertCount(0, $fixture['unloc']->getAttributes());
        $this->assertCount(0, $fixture['loc']->getAttributes());
    }

    public function testCreatesNewAttributeValue(): void
    {
        $fixture = $this->makeProductFixture(self::UUID, false);
        $fixture['unloc_prophecy']->addAttribute(Argument::that(
            static fn ($v): bool => $v instanceof ProductAttributeValueInterface && 7.5 === $v->getNumber()
        ))->shouldBeCalled()->willReturn($fixture['unloc_prophecy']->reveal());

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [self::UUID => 7.5]]);
    }

    public function testRemovesValueWhenNull(): void
    {
        $concretePdc = new ProductDimensionContent(new Product());

        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getUuid()->willReturn(self::UUID);
        $attribute->getKey()->willReturn('attr-1');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->isLocalized()->willReturn(false);

        $existingValue = new ProductAttributeValue($concretePdc, $attribute->reveal(), 'attr-1');
        $existingValue->setNumber(5.0);

        /** @var ObjectProphecy<ProductFamilyAttributeInterface> $familyAttribute */
        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $unloc */
        $unloc = $this->prophesize(ProductDimensionContentInterface::class);
        $unloc->getProductFamily()->willReturn($family->reveal());
        $unloc->getResource()->willReturn($this->prophesizeNonVariantResource());
        $unloc->getAttributes()->willReturn(new ArrayCollection([$existingValue]));
        $unloc->removeAttribute($existingValue)->shouldBeCalled()->willReturn($unloc->reveal());
        $unloc->addAttribute(Argument::cetera())->shouldNotBeCalled();
        /** @var ObjectProphecy<ProductDimensionContentInterface> $loc */
        $loc = $this->prophesize(ProductDimensionContentInterface::class);
        $loc->getAttributes()->willReturn(new ArrayCollection());

        $this->mapper->map($unloc->reveal(), $loc->reveal(), ['attributes' => [self::UUID => null]]);
    }

    public function testIsEmptyForEmptyString(): void
    {
        $concretePdc = new ProductDimensionContent(new Product());

        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getUuid()->willReturn(self::UUID);
        $attribute->getKey()->willReturn('attr-1');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->isLocalized()->willReturn(false);

        $existingValue = new ProductAttributeValue($concretePdc, $attribute->reveal(), 'attr-1');
        $existingValue->setNumber(3.0);

        /** @var ObjectProphecy<ProductFamilyAttributeInterface> $familyAttribute */
        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $unloc */
        $unloc = $this->prophesize(ProductDimensionContentInterface::class);
        $unloc->getProductFamily()->willReturn($family->reveal());
        $unloc->getResource()->willReturn($this->prophesizeNonVariantResource());
        $unloc->getAttributes()->willReturn(new ArrayCollection([$existingValue]));
        $unloc->removeAttribute($existingValue)->shouldBeCalled()->willReturn($unloc->reveal());
        /** @var ObjectProphecy<ProductDimensionContentInterface> $loc */
        $loc = $this->prophesize(ProductDimensionContentInterface::class);
        $loc->getAttributes()->willReturn(new ArrayCollection());

        $this->mapper->map($unloc->reveal(), $loc->reveal(), ['attributes' => [self::UUID => '']]);
    }

    public function testRequiredMissingThrows(): void
    {
        $fixture = $this->makeProductFixture(self::UUID, true);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(RequiredProductAttributeMissingException::class);
        $this->expectExceptionMessage('attr-1');

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [self::UUID => null]]);
    }

    public function testRequiredWithValuePasses(): void
    {
        $fixture = $this->makeProductFixture(self::UUID, true);
        $fixture['unloc_prophecy']->addAttribute(Argument::that(
            static fn ($v): bool => $v instanceof ProductAttributeValueInterface && 10.0 === $v->getNumber()
        ))->shouldBeCalled()->willReturn($fixture['unloc_prophecy']->reveal());

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [self::UUID => 10.0]]);

        $this->addToAssertionCount(1);
    }

    public function testUpdatesExistingValueInPlace(): void
    {
        $concretePdc = new ProductDimensionContent(new Product());

        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getUuid()->willReturn(self::UUID);
        $attribute->getKey()->willReturn('attr-1');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->isLocalized()->willReturn(false);

        $existingValue = new ProductAttributeValue($concretePdc, $attribute->reveal(), 'attr-1');
        $existingValue->setNumber(1.0);

        /** @var ObjectProphecy<ProductFamilyAttributeInterface> $familyAttribute */
        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $unloc */
        $unloc = $this->prophesize(ProductDimensionContentInterface::class);
        $unloc->getProductFamily()->willReturn($family->reveal());
        $unloc->getResource()->willReturn($this->prophesizeNonVariantResource());
        $unloc->getAttributes()->willReturn(new ArrayCollection([$existingValue]));
        $unloc->addAttribute(Argument::cetera())->shouldNotBeCalled();
        /** @var ObjectProphecy<ProductDimensionContentInterface> $loc */
        $loc = $this->prophesize(ProductDimensionContentInterface::class);
        $loc->getAttributes()->willReturn(new ArrayCollection());

        $this->mapper->map($unloc->reveal(), $loc->reveal(), ['attributes' => [self::UUID => 99.0]]);

        $this->assertSame(99.0, $existingValue->getNumber());
    }

    public function testCreatesLocalizedAttributeOnLocalizedDimensionContent(): void
    {
        $fixture = $this->makeProductFixture(self::UUID, false, true);

        $fixture['loc_prophecy']->addAttribute(Argument::that(
            static fn ($v): bool => $v instanceof ProductAttributeValueInterface && 7.5 === $v->getNumber()
        ))->shouldBeCalledOnce()->willReturn($fixture['loc_prophecy']->reveal());

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [self::UUID => 7.5]]);

        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testRemovesLocalizedValueFromLocalizedDimensionContent(): void
    {
        $concretePdc = new ProductDimensionContent(new Product());

        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getUuid()->willReturn(self::UUID);
        $attribute->getKey()->willReturn('attr-1');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->isLocalized()->willReturn(true);

        $existingValue = new ProductAttributeValue($concretePdc, $attribute->reveal(), 'attr-1');
        $existingValue->setNumber(5.0);

        /** @var ObjectProphecy<ProductFamilyAttributeInterface> $familyAttribute */
        $familyAttribute = $this->prophesize(ProductFamilyAttributeInterface::class);
        $familyAttribute->getAttribute()->willReturn($attribute->reveal());
        $familyAttribute->isRequired()->willReturn(false);

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute->reveal()]);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $unloc */
        $unloc = $this->prophesize(ProductDimensionContentInterface::class);
        $unloc->getProductFamily()->willReturn($family->reveal());
        $unloc->getResource()->willReturn($this->prophesizeNonVariantResource());
        $unloc->getAttributes()->willReturn(new ArrayCollection());
        $unloc->removeAttribute(Argument::cetera())->shouldNotBeCalled();
        /** @var ObjectProphecy<ProductDimensionContentInterface> $loc */
        $loc = $this->prophesize(ProductDimensionContentInterface::class);
        $loc->getAttributes()->willReturn(new ArrayCollection([$existingValue]));
        $loc->removeAttribute($existingValue)->shouldBeCalled()->willReturn($loc->reveal());

        $this->mapper->map($unloc->reveal(), $loc->reveal(), ['attributes' => [self::UUID => null]]);
    }

    public function testVariantSkipsRequiredNonVariantAttribute(): void
    {
        $fixture = $this->makeProductFixture(self::UUID, required: true, isVariantAttribute: false, productType: ProductInterface::TYPE_VARIANT);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [self::UUID => null]]);

        $this->addToAssertionCount(1);
    }

    public function testVariantStillEnforcesRequiredVariantAttribute(): void
    {
        $fixture = $this->makeProductFixture(self::UUID, required: true, isVariantAttribute: true, productType: ProductInterface::TYPE_VARIANT);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(RequiredProductAttributeMissingException::class);
        $this->expectExceptionMessage('attr-1');

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [self::UUID => null]]);
    }

    public function testProductWithoutVariantsEnforcesRequiredVariantAttribute(): void
    {
        $fixture = $this->makeProductFixture(self::UUID, required: true, isVariantAttribute: true, productType: ProductInterface::TYPE_PRODUCT);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(RequiredProductAttributeMissingException::class);
        $this->expectExceptionMessage('attr-1');

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [self::UUID => null]]);
    }

    public function testNonVariantProductStillEnforcesRequiredNonVariantAttribute(): void
    {
        $fixture = $this->makeProductFixture(self::UUID, required: true, isVariantAttribute: false, productType: ProductInterface::TYPE_PRODUCT);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(RequiredProductAttributeMissingException::class);
        $this->expectExceptionMessage('attr-1');

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [self::UUID => null]]);
    }

    /**
     * Regression: enforcing a variant axis against the product made a product with variants
     * impossible to save.
     */
    public function testProductWithVariantsSkipsRequiredVariantAttribute(): void
    {
        $fixture = $this->makeProductFixture(self::UUID, required: true, isVariantAttribute: true, productType: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [self::UUID => null]]);

        $this->addToAssertionCount(1);
    }

    public function testCreatesOneRowPerFieldOfAMultiFieldAttribute(): void
    {
        $fixture = $this->makeRealFixture('range');

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$fixture['attribute']->getUuid() => ['from' => 1.5, 'to' => 9]]]);

        $rows = $this->rowsByValueKey($fixture['unloc']);
        $this->assertSame(['from', 'to'], \array_keys($rows));
        $this->assertSame(1.5, $rows['from']->getNumber());
        $this->assertSame(9.0, $rows['to']->getNumber());
        $this->assertSame($fixture['familyAttribute'], $rows['from']->getProductFamilyAttribute());
        $this->assertCount(0, $fixture['loc']->getAttributes());
    }

    public function testUpdatesCreatesAndRemovesRowsPerValueKey(): void
    {
        $fixture = $this->makeRealFixture('range');
        $uuid = $fixture['attribute']->getUuid();
        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$uuid => ['from' => 1, 'to' => 2]]]);
        $from = $this->rowsByValueKey($fixture['unloc'])['from'];

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$uuid => ['from' => 5]]]);

        $rows = $this->rowsByValueKey($fixture['unloc']);
        $this->assertSame(['from'], \array_keys($rows));
        $this->assertSame($from, $rows['from']);
        $this->assertSame(5.0, $from->getNumber());

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$uuid => ['from' => 5, 'to' => 7]]]);

        $this->assertSame(['from', 'to'], \array_keys($this->rowsByValueKey($fixture['unloc'])));
        $this->assertSame(7.0, $this->rowsByValueKey($fixture['unloc'])['to']->getNumber());

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$uuid => null]]);

        $this->assertCount(0, $fixture['unloc']->getAttributes());
    }

    public function testStoresALocalizedMultiFieldAttributeOnTheLocalizedDimensionContent(): void
    {
        $fixture = $this->makeRealFixture('range', localized: true);

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$fixture['attribute']->getUuid() => ['from' => 1, 'to' => 2]]]);

        $this->assertCount(0, $fixture['unloc']->getAttributes());
        $this->assertSame(['from', 'to'], \array_keys($this->rowsByValueKey($fixture['loc'])));
    }

    public function testRowsOfAMultiSelectAreKeyedByOption(): void
    {
        $fixture = $this->makeRealFixture('multi_options');
        $attribute = $fixture['attribute'];
        $red = new AttributeOption($attribute, 'red');
        $blue = new AttributeOption($attribute, 'blue');
        $attribute->addOption($red);
        $attribute->addOption($blue);
        $uuid = $attribute->getUuid();

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$uuid => ['red', 'blue']]]);

        $rows = $this->rowsByValueKey($fixture['unloc']);
        $this->assertSame(['red', 'blue'], \array_keys($rows));
        $this->assertSame($red, $rows['red']->getAttributeOption());

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$uuid => ['blue']]]);

        $this->assertSame(['blue'], \array_keys($this->rowsByValueKey($fixture['unloc'])));
    }

    public function testInvalidValueChangesNoRow(): void
    {
        $fixture = $this->makeRealFixture('range');
        $uuid = $fixture['attribute']->getUuid();
        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$uuid => ['from' => 1, 'to' => 2]]]);

        try {
            $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$uuid => ['from' => 5, 'to' => 'not a number']]]);
            $this->fail('The invalid value was accepted.');
        } catch (\InvalidArgumentException) {
        }

        $rows = $this->rowsByValueKey($fixture['unloc']);
        $this->assertSame(['from', 'to'], \array_keys($rows));
        $this->assertSame(1.0, $rows['from']->getNumber());
        $this->assertSame(2.0, $rows['to']->getNumber());
    }

    public function testRequiredMultiFieldAttributeWithoutARowThrows(): void
    {
        $fixture = $this->makeRealFixture('range', required: true);

        $this->expectException(RequiredProductAttributeMissingException::class);
        $this->expectExceptionMessage('range');

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$fixture['attribute']->getUuid() => ['from' => null, 'to' => null]]]);
    }

    public function testRequiredMultiFieldAttributeWithOneRowPasses(): void
    {
        $fixture = $this->makeRealFixture('range', required: true);

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [$fixture['attribute']->getUuid() => ['from' => 1]]]);

        $this->assertSame(['from'], \array_keys($this->rowsByValueKey($fixture['unloc'])));
    }

    /**
     * @return array<string, ProductAttributeValueInterface>
     */
    private function rowsByValueKey(ProductDimensionContentInterface $dimensionContent): array
    {
        $rows = [];
        foreach ($dimensionContent->getAttributes() as $row) {
            $rows[$row->getValueKey()] = $row;
        }

        return $rows;
    }

    /**
     * Real objects, so the rows the mapper creates, keeps and removes are the ones in the collections.
     *
     * @return array{
     *     unloc: ProductDimensionContent,
     *     loc: ProductDimensionContent,
     *     attribute: Attribute,
     *     familyAttribute: ProductFamilyAttribute,
     * }
     */
    private function makeRealFixture(string $attributeType, bool $required = false, bool $localized = false): array
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey($attributeType);
        $attribute->setType($attributeType);
        $attribute->setLocalized($localized);

        $family = new ProductFamily();
        $familyAttribute = new ProductFamilyAttribute($family, $attribute);
        $familyAttribute->setRequired($required);
        $family->addFamilyAttribute($familyAttribute);

        $product = new Product();
        $unloc = new ProductDimensionContent($product);
        $unloc->setProductFamily($family);

        return [
            'unloc' => $unloc,
            'loc' => new ProductDimensionContent($product),
            'attribute' => $attribute,
            'familyAttribute' => $familyAttribute,
        ];
    }

    private function prophesizeNonVariantResource(): ProductInterface
    {
        /** @var ObjectProphecy<ProductInterface> $resource */
        $resource = $this->prophesize(ProductInterface::class);
        $resource->getType()->willReturn(ProductInterface::TYPE_PRODUCT);

        return $resource->reveal();
    }

    /**
     * @return array{
     *     unloc_prophecy: ObjectProphecy<ProductDimensionContentInterface>,
     *     loc_prophecy: ObjectProphecy<ProductDimensionContentInterface>,
     *     unloc: ProductDimensionContentInterface,
     *     loc: ProductDimensionContentInterface,
     *     attribute: AttributeInterface,
     *     familyAttribute: ProductFamilyAttributeInterface,
     * }
     */
    private function makeProductFixture(
        string $attributeUuid,
        bool $required,
        bool $localized = false,
        bool $isVariantAttribute = false,
        string $productType = ProductInterface::TYPE_PRODUCT,
    ): array {
        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getUuid()->willReturn($attributeUuid);
        $attribute->getKey()->willReturn('attr-1');
        $attribute->getType()->willReturn(AttributeInterface::TYPE_NUMBER);
        $attribute->isLocalized()->willReturn($localized);

        // A real family attribute, so the tests run against its own availability rule.
        $familyAttribute = new ProductFamilyAttribute(
            $this->prophesize(ProductFamilyInterface::class)->reveal(),
            $attribute->reveal(),
        );
        $familyAttribute->setVariantSpecific($isVariantAttribute);
        $familyAttribute->setRequired($required);

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute]);

        /** @var ObjectProphecy<ProductInterface> $resource */
        $resource = $this->prophesize(ProductInterface::class);
        $resource->getType()->willReturn($productType);

        /** @var ObjectProphecy<ProductDimensionContentInterface> $unloc */
        $unloc = $this->prophesize(ProductDimensionContentInterface::class);
        $unloc->getProductFamily()->willReturn($family->reveal());
        $unloc->getResource()->willReturn($resource->reveal());
        $unloc->getAttributes()->willReturn(new ArrayCollection());
        $unloc->addAttribute(Argument::cetera())->willReturn($unloc->reveal());
        $unloc->removeAttribute(Argument::cetera())->willReturn($unloc->reveal());
        /** @var ObjectProphecy<ProductDimensionContentInterface> $loc */
        $loc = $this->prophesize(ProductDimensionContentInterface::class);
        $loc->getAttributes()->willReturn(new ArrayCollection());
        $loc->addAttribute(Argument::cetera())->willReturn($loc->reveal());
        $loc->removeAttribute(Argument::cetera())->willReturn($loc->reveal());

        return [
            'unloc_prophecy' => $unloc,
            'loc_prophecy' => $loc,
            'unloc' => $unloc->reveal(),
            'loc' => $loc->reveal(),
            'attribute' => $attribute->reveal(),
            'familyAttribute' => $familyAttribute,
        ];
    }
}
