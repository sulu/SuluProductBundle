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

namespace Sulu\Product\Application\Mapper;

use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Exception\RequiredProductAttributeMissingException;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductInterface;

final class ProductAttributeValueMapper implements ProductMapperInterface
{
    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function mapProductData(ProductInterface $product, array $data): void
    {
        if (!\array_key_exists('attributes', $data)) {
            return;
        }

        /** @var array<int|string, mixed> $submitted */
        $submitted = $data['attributes'] ?? [];
        $family = $product->getProductFamily();

        /** @var array<int, ProductFamilyAttributeInterface> $familyAttributes */
        $familyAttributes = [];
        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            $familyAttributes[$familyAttribute->getAttribute()->getId()] = $familyAttribute;
        }

        /** @var array<int, ProductAttributeValueInterface> $existing */
        $existing = [];
        foreach ($product->getAttributes() as $value) {
            $existing[$value->getAttribute()->getId()] = $value;
        }

        foreach ($submitted as $attributeId => $raw) {
            $attributeId = (int) $attributeId;
            $familyAttribute = $familyAttributes[$attributeId] ?? null;

            if (null === $familyAttribute) {
                continue;
            }

            $attribute = $familyAttribute->getAttribute();
            $type = $this->attributeTypeRegistry->get($attribute->getType());
            $value = $existing[$attributeId] ?? null;

            if ($this->isEmpty($raw)) {
                if (null !== $value) {
                    $product->removeAttribute($value);
                    unset($existing[$attributeId]);
                }

                continue;
            }

            $isNew = null === $value;
            if (null === $value) {
                $value = new ProductAttributeValue($product, $attribute, $attribute->getKey());
                $value->setProductFamilyAttribute($familyAttribute);
            }

            $type->writeValue($value, $raw);

            if ($isNew) {
                $product->addAttribute($value);
                $existing[$attributeId] = $value;
            }
        }

        $this->assertRequiredSatisfied($familyAttributes, $existing);
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
