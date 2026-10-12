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
use Sulu\Product\Application\Attribute\AttributeRowData;
use Sulu\Product\Application\Attribute\AttributeValueConverter;
use Sulu\Product\Domain\Exception\RequiredProductAttributeMissingException;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;

class ProductAttributesDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly AttributeValueConverter $attributeValueConverter,
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

        /** @var array<string, ProductFamilyAttributeInterface> $familyAttributes */
        $familyAttributes = [];
        foreach ($productFamily->getFamilyAttributes() as $familyAttribute) {
            $familyAttributes[$familyAttribute->getAttribute()->getUuid()] = $familyAttribute;
        }

        /** @var array<string, array<string, ProductAttributeValueInterface>> $allExisting */
        $allExisting = [];
        foreach ([$unlocalizedDimensionContent, $localizedDimensionContent] as $dimensionContent) {
            foreach ($dimensionContent->getAttributes() as $row) {
                $allExisting[$row->getAttribute()->getUuid()][$row->getValueKey()] = $row;
            }
        }

        foreach ($submitted as $attributeUuid => $raw) {
            if (!\is_string($attributeUuid)) {
                continue;
            }

            $familyAttribute = $familyAttributes[$attributeUuid] ?? null;
            if (null === $familyAttribute) {
                continue;
            }

            $attribute = $familyAttribute->getAttribute();
            $targetDimensionContent = $attribute->isLocalized()
                ? $localizedDimensionContent
                : $unlocalizedDimensionContent;

            // Validates the whole value first, so a rejected one changes no row.
            $data = $this->attributeValueConverter->write($attribute, $raw);

            $allExisting[$attributeUuid] = $this->writeRows(
                $familyAttribute,
                $allExisting[$attributeUuid] ?? [],
                $data,
                $targetDimensionContent,
            );
        }

        $productType = $unlocalizedDimensionContent->getResource()->getType();

        $this->assertRequiredSatisfied($familyAttributes, $allExisting, $productType);
    }

    /**
     * Keeps the rows named by the data, creates the missing ones and removes the rest.
     *
     * @param array<string, ProductAttributeValueInterface> $existingRows by value key
     * @param array<string, AttributeRowData> $data by value key
     *
     * @return array<string, ProductAttributeValueInterface>
     */
    private function writeRows(
        ProductFamilyAttributeInterface $familyAttribute,
        array $existingRows,
        array $data,
        ProductDimensionContentInterface $targetDimensionContent,
    ): array {
        $attribute = $familyAttribute->getAttribute();

        $rows = [];
        foreach ($data as $valueKey => $rowData) {
            $row = $existingRows[$valueKey] ?? null;
            if (null === $row) {
                $row = new ProductAttributeValue($targetDimensionContent, $attribute, $attribute->getKey(), valueKey: $valueKey);
                $row->setProductFamilyAttribute($familyAttribute);
                $targetDimensionContent->addAttribute($row);
            }

            $row->setNumber($rowData->number);
            $row->setText($rowData->text);
            $row->setAttributeOption($rowData->option);

            $rows[$valueKey] = $row;
        }

        foreach (\array_diff_key($existingRows, $rows) as $row) {
            $targetDimensionContent->removeAttribute($row);
        }

        return $rows;
    }

    /**
     * @param array<string, ProductFamilyAttributeInterface> $familyAttributes
     * @param array<string, array<string, ProductAttributeValueInterface>> $rowsByAttribute
     *
     * @throws RequiredProductAttributeMissingException
     */
    private function assertRequiredSatisfied(array $familyAttributes, array $rowsByAttribute, string $productType): void
    {
        foreach ($familyAttributes as $attributeUuid => $familyAttribute) {
            if (!$familyAttribute->isRequiredFor($productType)) {
                continue;
            }

            if ([] === ($rowsByAttribute[$attributeUuid] ?? [])) {
                throw new RequiredProductAttributeMissingException($familyAttribute->getAttribute()->getKey());
            }
        }
    }
}
