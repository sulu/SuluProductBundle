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
            $type = $this->attributeTypeRegistry->get($attribute->getType());
            $existing = $allExisting[$attributeId] ?? null;

            if ($this->isEmpty($raw)) {
                if (null !== $existing) {
                    $unlocalizedDimensionContent->removeAttribute($existing);
                    unset($allExisting[$attributeId]);
                }

                continue;
            }

            $isNew = null === $existing;
            if (null === $existing) {
                $existing = new ProductAttributeValue($unlocalizedDimensionContent, $attribute, $attribute->getKey());
                $existing->setProductFamilyAttribute($familyAttribute);
            }

            $type->writeValue($existing, $raw);

            if ($isNew) {
                $unlocalizedDimensionContent->addAttribute($existing);
                $allExisting[$attributeId] = $existing;
            }
        }

        $this->assertRequiredSatisfied($familyAttributes, $allExisting);
    }

    /**
     * @param array<int, ProductFamilyAttributeInterface> $familyAttributes
     * @param array<int, ProductAttributeValueInterface> $values
     *
     * @throws RequiredProductAttributeMissingException
     */
    private function assertRequiredSatisfied(array $familyAttributes, array $values): void
    {
        foreach ($familyAttributes as $attributeId => $familyAttribute) {
            if (!$familyAttribute->isRequired()) {
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
