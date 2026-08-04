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

namespace Sulu\Product\Infrastructure\Sulu\Content\DataMapper;

use Sulu\Content\Application\ContentDataMapper\DataMapper\DataMapperInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Exception\RequiredProductAttributeMissingException;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductInterface;

class ProductAttributesDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    public function map(
        DimensionContentInterface $unlocalizedDimensionContent,
        DimensionContentInterface $localizedDimensionContent,
        array $data,
    ): void {
        if (!$unlocalizedDimensionContent instanceof ProductDimensionContentInterface) {
            return;
        }

        if (!$localizedDimensionContent instanceof ProductDimensionContentInterface) {
            return;
        }

        if (!\array_key_exists('attributes', $data)) {
            return;
        }

        $productFamily = $unlocalizedDimensionContent->getProductFamily();
        if (null === $productFamily) {
            return;
        }

        /** @var array<int|string, mixed> $submitted */
        $submitted = $data['attributes'] ?? [];

        /** @var array<int, ProductFamilyAttributeInterface> $familyAttributes */
        $familyAttributes = [];
        foreach ($productFamily->getFamilyAttributes() as $familyAttribute) {
            $familyAttributes[$familyAttribute->getAttribute()->getId()] = $familyAttribute;
        }

        /** @var array<int, ProductAttributeValueInterface> $allExisting */
        $allExisting = [];
        foreach ($unlocalizedDimensionContent->getAttributes() as $value) {
            $allExisting[$value->getAttribute()->getId()] = $value;
        }
        foreach ($localizedDimensionContent->getAttributes() as $value) {
            $allExisting[$value->getAttribute()->getId()] = $value;
        }

        foreach ($submitted as $attributeId => $raw) {
            if (!\is_int($attributeId) && !\ctype_digit((string) $attributeId)) {
                continue;
            }

            $attributeId = (int) $attributeId;
            $familyAttribute = $familyAttributes[$attributeId] ?? null;

            if (null === $familyAttribute) {
                continue;
            }

            $attribute = $familyAttribute->getAttribute();
            $targetDimensionContent = $attribute->isLocalized()
                ? $localizedDimensionContent
                : $unlocalizedDimensionContent;
            $type = $this->attributeTypeRegistry->get($attribute->getType());
            $existing = $allExisting[$attributeId] ?? null;

            if ($this->isEmpty($raw)) {
                if (null !== $existing) {
                    $targetDimensionContent->removeAttribute($existing);
                    unset($allExisting[$attributeId]);
                }

                continue;
            }

            $isNew = null === $existing;
            if (null === $existing) {
                $existing = new ProductAttributeValue($targetDimensionContent, $attribute, $attribute->getKey());
                $existing->setProductFamilyAttribute($familyAttribute);
            }

            $type->writeValue($existing, $raw);

            if ($isNew) {
                $targetDimensionContent->addAttribute($existing);
                $allExisting[$attributeId] = $existing;
            }
        }

        $isVariant = $unlocalizedDimensionContent->getResource()->isType(ProductInterface::TYPE_VARIANT);

        $this->assertRequiredSatisfied($familyAttributes, $allExisting, $isVariant);
    }

    /**
     * @param array<int, ProductFamilyAttributeInterface> $familyAttributes
     * @param array<int, ProductAttributeValueInterface> $values
     *
     * @throws RequiredProductAttributeMissingException
     */
    private function assertRequiredSatisfied(array $familyAttributes, array $values, bool $isVariant): void
    {
        foreach ($familyAttributes as $attributeId => $familyAttribute) {
            if (!$familyAttribute->isRequired()) {
                continue;
            }

            if ($isVariant && !$familyAttribute->isVariantSpecific()) {
                // Shared attributes are inherited from (and required on) the parent, not the variant.
                continue;
            }

            $value = $values[$attributeId] ?? null;
            if (null === $value || $this->isEmpty($value->getValue())) {
                throw new RequiredProductAttributeMissingException($familyAttribute->getAttribute()->getKey());
            }
        }
    }

    private function isEmpty(mixed $raw): bool
    {
        return null === $raw || '' === $raw;
    }
}
