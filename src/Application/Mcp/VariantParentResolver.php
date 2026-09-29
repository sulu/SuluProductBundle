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

namespace Sulu\Product\Application\Mcp;

use Sulu\Product\Domain\Exception\InvalidVariantParentException;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * Re-asserts the variant invariants SuluProductBundle enforces in ProductVariantController
 * rather than in its message handlers: ProductParentMapper resolves `parent` by UUID without
 * ever checking its type, so dispatching CreateProductMessage directly would nest a variant
 * under a plain product, or under another variant.
 *
 * @internal
 */
final readonly class VariantParentResolver
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductFamilyRepositoryInterface $productFamilyRepository,
    ) {
    }

    /**
     * @throws InvalidVariantParentException
     */
    public function resolveParent(string $parentUuid): ProductInterface
    {
        try {
            $parent = $this->productRepository->getOneBy(['uuid' => $parentUuid]);
        } catch (ProductNotFoundException $e) {
            throw new InvalidVariantParentException(
                \sprintf('Parent product not found: %s', $parentUuid),
                'Verify the parent UUID with sulu_product_list.',
                previous: $e,
            );
        }

        if (!$parent->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)) {
            throw new InvalidVariantParentException(
                \sprintf('Product "%s" cannot have variants (type "%s").', $parentUuid, $parent->getType()),
                \sprintf(
                    'Only products of type "%s" can hold variants, and variants cannot be nested. Create the parent with sulu_product_create(type: "%s").',
                    ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
                    ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
                ),
            );
        }

        return $parent;
    }

    /**
     * @throws InvalidVariantParentException
     */
    public function resolveFamily(ProductInterface $parent): ProductFamilyInterface
    {
        $family = $this->productFamilyRepository->findOneBy(['productUuid' => $parent->getUuid()]);

        if (null === $family) {
            throw new InvalidVariantParentException(
                \sprintf('Parent product "%s" has no product family assigned.', $parent->getUuid()),
                'Assign a product family to the parent with sulu_product_update before adding variants.',
            );
        }

        return $family;
    }

    /**
     * @throws InvalidVariantParentException
     */
    public function assertVariantOwnedByParent(string $parentUuid, string $variantUuid): ProductInterface
    {
        try {
            $variant = $this->productRepository->getOneBy(['uuid' => $variantUuid]);
        } catch (ProductNotFoundException $e) {
            throw new InvalidVariantParentException(
                \sprintf('Variant not found: %s', $variantUuid),
                'Verify the variant UUID with sulu_product_variant_list.',
                previous: $e,
            );
        }

        if ($parentUuid !== $variant->getParent()?->getUuid()) {
            throw new InvalidVariantParentException(
                \sprintf('Variant "%s" does not belong to parent "%s".', $variantUuid, $parentUuid),
                'List the parent\'s variants with sulu_product_variant_list to get the right UUIDs.',
            );
        }

        return $variant;
    }

    /**
     * @param array<array-key, mixed> $attributes
     *
     * @return array<array-key, mixed>
     */
    public function stripInheritedAttributes(ProductFamilyInterface $family, array $attributes): array
    {
        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            if (!$familyAttribute->isVariantSpecific()) {
                unset($attributes[$familyAttribute->getAttribute()->getId()]);
            }
        }

        return $attributes;
    }
}
