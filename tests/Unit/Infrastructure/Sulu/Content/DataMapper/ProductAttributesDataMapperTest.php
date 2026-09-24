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
use Sulu\Product\Application\AttributeType\AbstractAttributeType;
use Sulu\Product\Application\AttributeType\AttributeTypeInterface;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Domain\Exception\RequiredProductAttributeMissingException;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductAttributesDataMapper;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(ProductAttributesDataMapper::class)]
class ProductAttributesDataMapperTest extends TestCase
{
    use ProphecyTrait;

    private ProductAttributesDataMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ProductAttributesDataMapper(
            new AttributeTypeRegistry([new NumberAttributeType(), $this->createPartsType()]),
        );
    }

    public function testEarlyReturnWhenUnlocalizedNotProductDimensionContent(): void
    {
        $other = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($other->reveal(), $other->reveal(), ['attributes' => [1 => 5.0]]);

        $this->addToAssertionCount(1);
    }

    public function testEarlyReturnWhenLocalizedNotProductDimensionContent(): void
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $unloc */
        $unloc = $this->prophesize(ProductDimensionContentInterface::class);
        $locOther = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($unloc->reveal(), $locOther->reveal(), ['attributes' => [1 => 5.0]]);

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

        $this->mapper->map($unloc->reveal(), $loc->reveal(), ['attributes' => [1 => 5.0]]);

        $unloc->getAttributes()->shouldNotHaveBeenCalled();
        $this->addToAssertionCount(1);
    }

    public function testSkipsAttributeNotInFamily(): void
    {
        $fixture = $this->makeProductFixture(1, false);

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [99 => 5.0]]);

        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->addToAssertionCount(1);
    }

    public function testSkipsNonIntegerKeys(): void
    {
        $fixture = $this->makeProductFixture(1, false);

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => 7.5, '1_extra' => 'KILOGRAM']]);

        $fixture['unloc_prophecy']->addAttribute(Argument::that(
            static fn ($v): bool => $v instanceof ProductAttributeValueInterface && 7.5 === $v->getNumber()
        ))->shouldHaveBeenCalledOnce();
    }

    public function testCreatesNewAttributeValue(): void
    {
        $fixture = $this->makeProductFixture(1, false);
        $fixture['unloc_prophecy']->addAttribute(Argument::that(
            static fn ($v): bool => $v instanceof ProductAttributeValueInterface && 7.5 === $v->getNumber()
        ))->shouldBeCalled()->willReturn($fixture['unloc_prophecy']->reveal());

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => 7.5]]);
    }

    public function testRemovesValueWhenNull(): void
    {
        $concretePdc = new ProductDimensionContent(new Product());

        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(1);
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

        $this->mapper->map($unloc->reveal(), $loc->reveal(), ['attributes' => [1 => null]]);
    }

    public function testIsEmptyForEmptyString(): void
    {
        $concretePdc = new ProductDimensionContent(new Product());

        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(1);
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

        $this->mapper->map($unloc->reveal(), $loc->reveal(), ['attributes' => [1 => '']]);
    }

    public function testRequiredMissingThrows(): void
    {
        $fixture = $this->makeProductFixture(1, true);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(RequiredProductAttributeMissingException::class);
        $this->expectExceptionMessage('attr-1');

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => null]]);
    }

    public function testRequiredWithValuePasses(): void
    {
        $fixture = $this->makeProductFixture(1, true);
        $fixture['unloc_prophecy']->addAttribute(Argument::that(
            static fn ($v): bool => $v instanceof ProductAttributeValueInterface && 10.0 === $v->getNumber()
        ))->shouldBeCalled()->willReturn($fixture['unloc_prophecy']->reveal());

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => 10.0]]);

        $this->addToAssertionCount(1);
    }

    public function testUpdatesExistingValueInPlace(): void
    {
        $concretePdc = new ProductDimensionContent(new Product());

        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(1);
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

        $this->mapper->map($unloc->reveal(), $loc->reveal(), ['attributes' => [1 => 99.0]]);

        $this->assertSame(99.0, $existingValue->getNumber());
    }

    public function testCreatesLocalizedAttributeOnLocalizedDimensionContent(): void
    {
        $fixture = $this->makeProductFixture(1, false, true);

        $fixture['loc_prophecy']->addAttribute(Argument::that(
            static fn ($v): bool => $v instanceof ProductAttributeValueInterface && 7.5 === $v->getNumber()
        ))->shouldBeCalledOnce()->willReturn($fixture['loc_prophecy']->reveal());

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => 7.5]]);

        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testRemovesLocalizedValueFromLocalizedDimensionContent(): void
    {
        $concretePdc = new ProductDimensionContent(new Product());

        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(1);
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

        $this->mapper->map($unloc->reveal(), $loc->reveal(), ['attributes' => [1 => null]]);
    }

    public function testVariantSkipsRequiredNonVariantAttribute(): void
    {
        $fixture = $this->makeProductFixture(1, required: true, isVariantAttribute: false, productType: ProductInterface::TYPE_VARIANT);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => null]]);

        $this->addToAssertionCount(1);
    }

    public function testVariantStillEnforcesRequiredVariantAttribute(): void
    {
        $fixture = $this->makeProductFixture(1, required: true, isVariantAttribute: true, productType: ProductInterface::TYPE_VARIANT);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(RequiredProductAttributeMissingException::class);
        $this->expectExceptionMessage('attr-1');

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => null]]);
    }

    public function testProductWithoutVariantsEnforcesRequiredVariantAttribute(): void
    {
        $fixture = $this->makeProductFixture(1, required: true, isVariantAttribute: true, productType: ProductInterface::TYPE_PRODUCT);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(RequiredProductAttributeMissingException::class);
        $this->expectExceptionMessage('attr-1');

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => null]]);
    }

    public function testNonVariantProductStillEnforcesRequiredNonVariantAttribute(): void
    {
        $fixture = $this->makeProductFixture(1, required: true, isVariantAttribute: false, productType: ProductInterface::TYPE_PRODUCT);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(RequiredProductAttributeMissingException::class);
        $this->expectExceptionMessage('attr-1');

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => null]]);
    }

    /**
     * Regression: enforcing a variant axis against the product made a product with variants
     * impossible to save.
     */
    public function testProductWithVariantsSkipsRequiredVariantAttribute(): void
    {
        $fixture = $this->makeProductFixture(1, required: true, isVariantAttribute: true, productType: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $fixture['unloc_prophecy']->addAttribute(Argument::cetera())->shouldNotBeCalled();

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => null]]);

        $this->addToAssertionCount(1);
    }

    public function testWritesOneRowPerValueKey(): void
    {
        $fixture = $this->makePartsFixture([]);

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => ['from' => 1, 'to' => 2]]]);

        self::assertSame(['from' => 1.0, 'to' => 2.0], $this->numbersByValueKey($fixture['added']));
        self::assertSame([], $fixture['removed']->getArrayCopy());
    }

    public function testKeepsNamedRowsAndRemovesRowsNoLongerNamed(): void
    {
        $content = new ProductDimensionContent(new Product());
        $attribute = $this->prophesizePartsAttribute();
        $kept = new ProductAttributeValue($content, $attribute, 'attr-1', valueKey: 'a');
        $dropped = new ProductAttributeValue($content, $attribute, 'attr-1', valueKey: 'b');
        $fixture = $this->makePartsFixture([$kept, $dropped], $attribute);

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => ['a' => 5, 'c' => 7]]]);

        self::assertSame(5.0, $kept->getNumber());
        self::assertSame(['c' => 7.0], $this->numbersByValueKey($fixture['added']));
        self::assertSame([$dropped], $fixture['removed']->getArrayCopy());
    }

    public function testRemovesAllRowsWhenEveryPartIsEmpty(): void
    {
        $content = new ProductDimensionContent(new Product());
        $attribute = $this->prophesizePartsAttribute();
        $rows = [
            new ProductAttributeValue($content, $attribute, 'attr-1', valueKey: 'from'),
            new ProductAttributeValue($content, $attribute, 'attr-1', valueKey: 'to'),
        ];
        $fixture = $this->makePartsFixture($rows, $attribute);

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => ['from' => null, 'to' => '']]]);

        self::assertSame([], $fixture['added']->getArrayCopy());
        self::assertSame($rows, $fixture['removed']->getArrayCopy());
    }

    public function testRejectsAValueKeyTheColumnCannotHold(): void
    {
        $fixture = $this->makePartsFixture([]);

        try {
            $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => ['not a key' => 1]]]);
            self::fail('Expected the value key to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('"not a key"', $exception->getMessage());
        }

        self::assertSame([], $fixture['added']->getArrayCopy());
    }

    public function testRequiredReadsTheValueOfAllRows(): void
    {
        $content = new ProductDimensionContent(new Product());
        $attribute = $this->prophesizePartsAttribute();
        $row = new ProductAttributeValue($content, $attribute, 'attr-1', valueKey: 'from');
        $row->setNumber(1.0);
        $fixture = $this->makePartsFixture([$row], $attribute, required: true);

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => []]);

        $this->expectException(RequiredProductAttributeMissingException::class);

        $this->mapper->map($fixture['unloc'], $fixture['loc'], ['attributes' => [1 => ['from' => null]]]);
    }

    /**
     * Stores each entry of an array value in a row of its own, under the entry's key.
     */
    private function createPartsType(): AttributeTypeInterface
    {
        return new class() extends AbstractAttributeType {
            public function getKey(): string
            {
                return 'parts';
            }

            public function getFormKey(): string
            {
                return 'product_attribute_parts';
            }

            public function getValueKeys(AttributeInterface $attribute, mixed $raw): array
            {
                Assert::isArray($raw);

                return \array_map(strval(...), \array_keys($raw));
            }

            public function readValue(array $rows): mixed
            {
                return \array_map(static fn (ProductAttributeValueInterface $row): ?float => $row->getNumber(), $rows);
            }

            public function writeValue(array $rows, mixed $raw): void
            {
                Assert::isArray($raw);

                foreach ($rows as $valueKey => $row) {
                    Assert::numeric($raw[$valueKey]);
                    $row->setNumber((float) $raw[$valueKey]);
                }
            }
        };
    }

    private function prophesizePartsAttribute(): AttributeInterface
    {
        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn(1);
        $attribute->getKey()->willReturn('attr-1');
        $attribute->getType()->willReturn('parts');
        $attribute->isLocalized()->willReturn(false);

        return $attribute->reveal();
    }

    /**
     * @param list<ProductAttributeValueInterface> $existingRows
     *
     * @return array{unloc: ProductDimensionContentInterface, loc: ProductDimensionContentInterface, added: \ArrayObject<int, ProductAttributeValueInterface>, removed: \ArrayObject<int, ProductAttributeValueInterface>}
     */
    private function makePartsFixture(array $existingRows, ?AttributeInterface $attribute = null, bool $required = false): array
    {
        $familyAttribute = new ProductFamilyAttribute(
            $this->prophesize(ProductFamilyInterface::class)->reveal(),
            $attribute ?? $this->prophesizePartsAttribute(),
        );
        $familyAttribute->setRequired($required);

        /** @var ObjectProphecy<ProductFamilyInterface> $family */
        $family = $this->prophesize(ProductFamilyInterface::class);
        $family->getFamilyAttributes()->willReturn([$familyAttribute]);

        $added = new \ArrayObject();
        $removed = new \ArrayObject();

        /** @var ObjectProphecy<ProductDimensionContentInterface> $unloc */
        $unloc = $this->prophesize(ProductDimensionContentInterface::class);
        $unloc->getProductFamily()->willReturn($family->reveal());
        $unloc->getResource()->willReturn($this->prophesizeNonVariantResource());
        $unloc->getAttributes()->willReturn(new ArrayCollection($existingRows));
        $unloc->addAttribute(Argument::type(ProductAttributeValueInterface::class))->will(function(array $arguments) use ($added) {
            $added[] = $arguments[0];

            return $this;
        });
        $unloc->removeAttribute(Argument::type(ProductAttributeValueInterface::class))->will(function(array $arguments) use ($removed) {
            $removed[] = $arguments[0];

            return $this;
        });

        /** @var ObjectProphecy<ProductDimensionContentInterface> $loc */
        $loc = $this->prophesize(ProductDimensionContentInterface::class);
        $loc->getAttributes()->willReturn(new ArrayCollection());

        return ['unloc' => $unloc->reveal(), 'loc' => $loc->reveal(), 'added' => $added, 'removed' => $removed];
    }

    /**
     * @param \ArrayObject<int, ProductAttributeValueInterface> $rows
     *
     * @return array<string, float|null>
     */
    private function numbersByValueKey(\ArrayObject $rows): array
    {
        $numbers = [];
        foreach ($rows as $row) {
            $numbers[$row->getValueKey()] = $row->getNumber();
        }

        return $numbers;
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
        int $attributeId,
        bool $required,
        bool $localized = false,
        bool $isVariantAttribute = false,
        string $productType = ProductInterface::TYPE_PRODUCT,
    ): array {
        /** @var ObjectProphecy<AttributeInterface> $attribute */
        $attribute = $this->prophesize(AttributeInterface::class);
        $attribute->getId()->willReturn($attributeId);
        $attribute->getKey()->willReturn('attr-' . $attributeId);
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
