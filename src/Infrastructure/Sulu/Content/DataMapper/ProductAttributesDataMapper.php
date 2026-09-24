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
use Webmozart\Assert\Assert;

class ProductAttributesDataMapper implements DataMapperInterface
{
    // Fits the 32 characters of the valueKey column.
    private const VALUE_KEY_PATTERN = '/^[A-Za-z0-9_.-]{1,32}$/';

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

        /** @var array<int, array<string, ProductAttributeValueInterface>> $allExisting */
        $allExisting = [];
        foreach ([$unlocalizedDimensionContent, $localizedDimensionContent] as $dimensionContent) {
            foreach ($dimensionContent->getAttributes() as $row) {
                $allExisting[$row->getAttribute()->getId()][$row->getValueKey()] = $row;
            }
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

            $targetDimensionContent = $familyAttribute->getAttribute()->isLocalized()
                ? $localizedDimensionContent
                : $unlocalizedDimensionContent;
            $existingRows = $allExisting[$attributeId] ?? [];
            unset($allExisting[$attributeId]);

            if ($this->isEmpty($raw)) {
                $this->removeRows($targetDimensionContent, $existingRows);

                continue;
            }

            $allExisting[$attributeId] = $this->writeRows($familyAttribute, $existingRows, $raw, $targetDimensionContent);
        }

        $productType = $unlocalizedDimensionContent->getResource()->getType();

        $this->assertRequiredSatisfied($familyAttributes, $allExisting, $productType);
    }

    /**
     * Keeps the rows the type names for the value, creates the missing ones and removes the rest.
     * New rows join the dimension content only once the type accepted the value.
     *
     * @param array<string, ProductAttributeValueInterface> $existingRows
     *
     * @return array<string, ProductAttributeValueInterface>
     */
    private function writeRows(
        ProductFamilyAttributeInterface $familyAttribute,
        array $existingRows,
        mixed $raw,
        ProductDimensionContentInterface $targetDimensionContent,
    ): array {
        $attribute = $familyAttribute->getAttribute();
        $type = $this->attributeTypeRegistry->get($attribute->getType());

        $rows = [];
        $newRows = [];
        foreach ($type->getValueKeys($attribute, $raw) as $valueKey) {
            Assert::regex($valueKey, self::VALUE_KEY_PATTERN, \sprintf('Attribute type "%s" named the invalid value key "%s".', $type->getKey(), $valueKey));

            $row = $existingRows[$valueKey] ?? null;
            if (null === $row) {
                $row = new ProductAttributeValue($targetDimensionContent, $attribute, $attribute->getKey(), valueKey: $valueKey);
                $row->setProductFamilyAttribute($familyAttribute);
                $newRows[] = $row;
            }

            $rows[$valueKey] = $row;
        }

        $type->writeValue($rows, $raw);

        foreach ($newRows as $row) {
            $targetDimensionContent->addAttribute($row);
        }

        $this->removeRows($targetDimensionContent, \array_diff_key($existingRows, $rows));

        return $rows;
    }

    /**
     * @param array<string, ProductAttributeValueInterface> $rows
     */
    private function removeRows(ProductDimensionContentInterface $dimensionContent, array $rows): void
    {
        foreach ($rows as $row) {
            $dimensionContent->removeAttribute($row);
        }
    }

    /**
     * @param array<int, ProductFamilyAttributeInterface> $familyAttributes
     * @param array<int, array<string, ProductAttributeValueInterface>> $rowsByAttribute
     *
     * @throws RequiredProductAttributeMissingException
     */
    private function assertRequiredSatisfied(array $familyAttributes, array $rowsByAttribute, string $productType): void
    {
        foreach ($familyAttributes as $attributeId => $familyAttribute) {
            if (!$familyAttribute->isRequired()) {
                continue;
            }

            if (!$familyAttribute->isAvailable($productType)) {
                continue;
            }

            $attribute = $familyAttribute->getAttribute();
            $value = $this->attributeTypeRegistry->get($attribute->getType())->readValue($rowsByAttribute[$attributeId] ?? []);
            if ($this->isEmpty($value)) {
                throw new RequiredProductAttributeMissingException($attribute->getKey());
            }
        }
    }

    /**
     * A structured value like a range or a selection is empty when all of its parts are.
     */
    private function isEmpty(mixed $raw): bool
    {
        if (\is_array($raw)) {
            return [] === \array_filter($raw, fn (mixed $part): bool => !$this->isEmpty($part));
        }

        return null === $raw || '' === $raw;
    }
}
