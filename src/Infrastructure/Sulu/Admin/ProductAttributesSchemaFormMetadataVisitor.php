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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataVisitorInterface;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\ConstMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\IfThenElseMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\SchemaMetadata;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;

/**
 * Puts the validation of the attribute values into the JSON schema of the product forms, the way
 * block types are validated: the details form carries one "if productFamily is X then attributes
 * match X" branch per family, the variant form the axis attributes of the parent's family.
 *
 * @internal
 */
class ProductAttributesSchemaFormMetadataVisitor implements FormMetadataVisitorInterface
{
    public function __construct(
        private readonly ProductFamilyRepositoryInterface $productFamilyRepository,
        private readonly AttributeFieldFactory $attributeFieldFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $metadataOptions
     */
    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        $key = $formMetadata->getKey();
        if (ProductInterface::FORM_KEY !== $key && ProductInterface::FORM_KEY_VARIANT !== $key) {
            return;
        }

        $formMetadata->setCacheable(false);

        $schema = ProductInterface::FORM_KEY === $key
            ? $this->buildDetailsSchema($metadataOptions, $locale)
            : $this->buildVariantSchema($metadataOptions, $locale);

        if (null !== $schema) {
            $formMetadata->setSchema($formMetadata->getSchema()->merge($schema));
        }
    }

    /**
     * @param array<string, mixed> $metadataOptions
     */
    private function buildDetailsSchema(array $metadataOptions, string $locale): ?SchemaMetadata
    {
        $select = [ProductFamilyRepositoryInterface::GROUP_SELECT_PRODUCT_FAMILY_FORM => true];
        $branches = [];

        foreach ($this->resolveFamilies($metadataOptions, $select) as $family) {
            $uuid = $family->getUuid();
            if (null === $uuid) {
                continue;
            }

            $attributes = $this->buildAttributesProperty($family, false, $locale);
            if (null === $attributes) {
                continue;
            }

            $branches[] = new IfThenElseMetadata(
                new SchemaMetadata([new PropertyMetadata('productFamily', true, new ConstMetadata($uuid))]),
                new SchemaMetadata([$attributes]),
            );
        }

        return [] === $branches ? null : new SchemaMetadata([], [], $branches);
    }

    /**
     * The edit form fixes the family (product_details.xml disables the field once there is an id),
     * so it needs its own branch only. The add form has no id and validates against every family.
     *
     * @param array<string, mixed> $metadataOptions
     * @param array<string, bool> $select
     *
     * @return iterable<ProductFamilyInterface>
     */
    private function resolveFamilies(array $metadataOptions, array $select): iterable
    {
        $productUuid = $metadataOptions['id'] ?? null;

        if (!\is_string($productUuid) || '' === $productUuid) {
            return $this->productFamilyRepository->findBy([], $select);
        }

        $family = $this->productFamilyRepository->findOneBy(['productUuid' => $productUuid], $select);

        return null === $family ? [] : [$family];
    }

    /**
     * @param array<string, mixed> $metadataOptions
     */
    private function buildVariantSchema(array $metadataOptions, string $locale): ?SchemaMetadata
    {
        $parentUuid = $metadataOptions['parentId'] ?? null;
        if (!\is_string($parentUuid) || '' === $parentUuid) {
            return null;
        }

        $family = $this->productFamilyRepository->findOneBy(
            ['productUuid' => $parentUuid],
            [ProductFamilyRepositoryInterface::GROUP_SELECT_PRODUCT_FAMILY_FORM => true],
        );
        if (null === $family) {
            return null;
        }

        $attributes = $this->buildAttributesProperty($family, true, $locale);

        return null === $attributes ? null : new SchemaMetadata([$attributes]);
    }

    /**
     * The "attributes" object is mandatory as soon as one of its attributes is, so an untouched
     * form fails the same way a form with an emptied value does.
     */
    private function buildAttributesProperty(ProductFamilyInterface $family, bool $variant, string $locale): ?PropertyMetadata
    {
        $properties = [];
        $mandatory = false;

        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            if ($familyAttribute->isVariantSpecific() !== $variant) {
                continue;
            }

            $property = $this->attributeFieldFactory->buildSchemaProperty($familyAttribute, $locale);
            if (null === $property) {
                continue;
            }

            $properties[] = $property;
            $mandatory = $mandatory || $property->isMandatory();
        }

        if ([] === $properties) {
            return null;
        }

        return new PropertyMetadata('attributes', $mandatory, new SchemaMetadata($properties));
    }
}
