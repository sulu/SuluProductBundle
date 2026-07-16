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

namespace Sulu\Product\Infrastructure\Sulu\Admin;

use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;

/**
 * Builds the `variantTitle` column value for the Variants tab list
 * ({@see \Sulu\Product\UserInterface\Controller\Admin\ProductVariantController::cgetAction}).
 *
 * @internal
 */
final class ProductVariantTitleBuilder
{
    public function build(ProductDimensionContentInterface $content, string $locale): string
    {
        $family = $content->getProductFamily();
        if (null === $family) {
            return '';
        }

        /** @var array<int, ProductAttributeValueInterface> $valuesByAttributeId */
        $valuesByAttributeId = [];
        foreach ($content->getAttributes() as $attributeValue) {
            $valuesByAttributeId[$attributeValue->getAttribute()->getId()] = $attributeValue;
        }

        /** @var ProductFamilyAttributeInterface[] $variantFamilyAttributes */
        $variantFamilyAttributes = \array_values(\array_filter(
            $family->getFamilyAttributes(),
            static fn (ProductFamilyAttributeInterface $familyAttribute): bool => $familyAttribute->isVariant(),
        ));

        \usort(
            $variantFamilyAttributes,
            static fn (ProductFamilyAttributeInterface $a, ProductFamilyAttributeInterface $b): int => $a->getAttribute()->getPosition() <=> $b->getAttribute()->getPosition(),
        );

        $labels = [];
        foreach ($variantFamilyAttributes as $familyAttribute) {
            $attribute = $familyAttribute->getAttribute();
            $attributeValue = $valuesByAttributeId[$attribute->getId()] ?? null;
            if (null === $attributeValue) {
                continue;
            }

            $label = $this->resolveLabel($attribute, $attributeValue, $locale);
            if ('' !== $label) {
                $labels[] = $label;
            }
        }

        return \implode(' / ', $labels);
    }

    private function resolveLabel(
        AttributeInterface $attribute,
        ProductAttributeValueInterface $attributeValue,
        string $locale,
    ): string {
        if (AttributeInterface::TYPE_OPTIONS === $attribute->getType()) {
            $optionKey = $attributeValue->getAttributeOptionKey();
            if (null === $optionKey) {
                return '';
            }

            $option = $attribute->getOption($optionKey);
            if (null === $option) {
                return $optionKey;
            }

            return $option->getTranslation($locale)?->getName() ?? $optionKey;
        }

        $value = $attributeValue->getValue();

        return \is_scalar($value) ? (string) $value : '';
    }
}
