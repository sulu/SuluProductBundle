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

namespace Sulu\Product\Tests\Unit\Application\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Mcp\VariantParentResolver;
use Sulu\Product\Domain\Exception\InvalidVariantParentException;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

#[CoversClass(VariantParentResolver::class)]
final class VariantParentResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;
    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;
    private VariantParentResolver $resolver;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);

        $this->resolver = new VariantParentResolver(
            $this->productRepository->reveal(),
            $this->productFamilyRepository->reveal(),
        );
    }

    public function testResolveParentReturnsAProductThatCanHoldVariants(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->productRepository->getOneBy(['uuid' => 'parent-uuid'])->willReturn($parent);

        $this->assertSame($parent, $this->resolver->resolveParent('parent-uuid'));
    }

    public function testResolveParentRejectsAPlainProduct(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT);

        $this->productRepository->getOneBy(['uuid' => 'parent-uuid'])->willReturn($parent);

        $this->expectException(InvalidVariantParentException::class);
        $this->expectExceptionMessage('cannot have variants');

        $this->resolver->resolveParent('parent-uuid');
    }

    public function testResolveParentRejectsAVariantAsParent(): void
    {
        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);

        $this->productRepository->getOneBy(['uuid' => 'variant-uuid'])->willReturn($variant);

        $this->expectException(InvalidVariantParentException::class);
        $this->expectExceptionMessage('cannot have variants');

        $this->resolver->resolveParent('variant-uuid');
    }

    public function testResolveParentRejectsAMissingProduct(): void
    {
        $this->productRepository->getOneBy(Argument::cetera())
            ->willThrow(new ProductNotFoundException(['uuid' => 'nope']));

        $this->expectException(InvalidVariantParentException::class);
        $this->expectExceptionMessage('Parent product not found');

        $this->resolver->resolveParent('nope');
    }

    public function testResolveFamilyReturnsTheParentsFamily(): void
    {
        $parent = new Product('parent-uuid');
        $family = new ProductFamily();

        $this->productFamilyRepository->findOneBy(['productUuid' => 'parent-uuid'])->willReturn($family);

        $this->assertSame($family, $this->resolver->resolveFamily($parent));
    }

    public function testResolveFamilyRejectsAParentWithoutFamily(): void
    {
        $parent = new Product('parent-uuid');

        $this->productFamilyRepository->findOneBy(Argument::cetera())->willReturn(null);

        $this->expectException(InvalidVariantParentException::class);
        $this->expectExceptionMessage('has no product family assigned');

        $this->resolver->resolveFamily($parent);
    }

    public function testAssertVariantOwnedByParentAcceptsAChild(): void
    {
        $parent = new Product('parent-uuid');
        $variant = new Product('variant-uuid');
        $variant->setParent($parent);

        $this->productRepository->getOneBy(['uuid' => 'variant-uuid'])->willReturn($variant);

        $this->assertSame($variant, $this->resolver->assertVariantOwnedByParent('parent-uuid', 'variant-uuid'));
    }

    public function testAssertVariantOwnedByParentRejectsAForeignVariant(): void
    {
        $otherParent = new Product('other-parent');
        $variant = new Product('variant-uuid');
        $variant->setParent($otherParent);

        $this->productRepository->getOneBy(['uuid' => 'variant-uuid'])->willReturn($variant);

        $this->expectException(InvalidVariantParentException::class);
        $this->expectExceptionMessage('does not belong to parent');

        $this->resolver->assertVariantOwnedByParent('parent-uuid', 'variant-uuid');
    }

    public function testStripInheritedAttributesKeepsOnlyVariantAxes(): void
    {
        $family = $this->familyWithAttributes([
            'shared-uuid' => false, // shared -> stripped
            'axis-uuid' => true, // variant axis -> kept
        ]);

        $stripped = $this->resolver->stripInheritedAttributes($family, [
            'shared-uuid' => 'shared value',
            'axis-uuid' => 'red',
            'unknown-uuid' => 'unknown attribute is left untouched',
        ]);

        $this->assertSame(['axis-uuid' => 'red', 'unknown-uuid' => 'unknown attribute is left untouched'], $stripped);
    }

    /**
     * @param array<string, bool> $variantSpecificByAttributeUuid
     */
    private function familyWithAttributes(array $variantSpecificByAttributeUuid): ProductFamilyInterface
    {
        $family = new ProductFamily();
        $group = new AttributeGroup();

        foreach ($variantSpecificByAttributeUuid as $attributeUuid => $variantSpecific) {
            $attribute = new Attribute($group, $attributeUuid);

            $familyAttribute = new ProductFamilyAttribute($family, $attribute);
            $familyAttribute->setVariantSpecific($variantSpecific);
            $family->addFamilyAttribute($familyAttribute);
        }

        return $family;
    }

    public function testAssertVariantOwnedByParentRejectsAnUnknownVariant(): void
    {
        $this->productRepository->getOneBy(['uuid' => 'nope'])->willThrow(new ProductNotFoundException(['uuid' => 'nope']));

        try {
            $this->resolver->assertVariantOwnedByParent('parent-uuid', 'nope');
            $this->fail('An unknown variant must be rejected.');
        } catch (InvalidVariantParentException $e) {
            $this->assertSame('Variant not found: nope', $e->getMessage());
            $this->assertStringContainsString('sulu_product_variant_list', $e->getHint());
            $this->assertInstanceOf(ProductNotFoundException::class, $e->getPrevious());
        }
    }
}
