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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapperRegistry;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\SchemaMetadata;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Fills the "product_attributes" form with one section per attribute group and one field per family
 * attribute. The family comes from the metadata options, so the admin can request the form for a
 * family before the product is saved.
 *
 * Options: "productFamily" (family uuid) or "product" (product uuid, resolved to its family);
 * "variant" truthy keeps only axis attributes, otherwise only shared ones.
 *
 * The JSON schema carries the field constraints (required, min, max) keyed by field name, so the
 * admin can validate the values before saving.
 *
 * @internal
 */
class ProductAttributeFormMetadataVisitor implements FormMetadataVisitorInterface
{
    public const FORM_KEY = 'product_attributes';

    public function __construct(
        private readonly ProductFamilyRepositoryInterface $productFamilyRepository,
        private readonly AttributeFieldFactory $attributeFieldFactory,
        private readonly TranslatorInterface $translator,
        private readonly PropertyMetadataMapperRegistry $propertyMetadataMapperRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $metadataOptions
     */
    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (self::FORM_KEY !== $formMetadata->getKey()) {
            return;
        }

        // The response depends on family configuration that can change without the URL
        // changing, so it must not be cached by the HTTP layer or the browser.
        $formMetadata->setCacheable(false);

        $family = $this->resolveFamily($metadataOptions);
        if (null === $family) {
            return;
        }

        $variant = \filter_var($metadataOptions['variant'] ?? false, \FILTER_VALIDATE_BOOLEAN);

        /** @var array<int, SectionMetadata> $sections */
        $sections = [];
        /** @var list<PropertyMetadata> $schemaProperties */
        $schemaProperties = [];

        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            if ($familyAttribute->isVariantSpecific() !== $variant) {
                continue;
            }

            $field = $this->attributeFieldFactory->build($familyAttribute, $locale);
            if (null === $field) {
                continue;
            }

            $group = $familyAttribute->getAttribute()->getGroup();
            $groupId = $group->getId();
            $sections[$groupId] ??= $this->createGroupSection($group, $locale);
            $sections[$groupId]->addItem($field);

            $schemaProperties[] = $this->propertyMetadataMapperRegistry->has($field->getType())
                ? $this->propertyMetadataMapperRegistry->get($field->getType())->mapPropertyMetadata($field)
                : new PropertyMetadata($field->getName(), $field->isRequired());
        }

        $items = $formMetadata->getItems();
        foreach ($sections as $section) {
            $items[$section->getName()] = $section;
        }
        $formMetadata->setItems($items);

        if ([] !== $schemaProperties) {
            $formMetadata->setSchema($formMetadata->getSchema()->merge(new SchemaMetadata($schemaProperties)));
        }
    }

    /**
     * @param array<string, mixed> $metadataOptions
     */
    private function resolveFamily(array $metadataOptions): ?ProductFamilyInterface
    {
        $select = [ProductFamilyRepositoryInterface::GROUP_SELECT_PRODUCT_FAMILY_FORM => true];

        $familyUuid = $metadataOptions['productFamily'] ?? null;
        if (\is_string($familyUuid) && '' !== $familyUuid) {
            return $this->productFamilyRepository->findOneBy(['uuid' => $familyUuid], $select);
        }

        $productUuid = $metadataOptions['product'] ?? null;
        if (\is_string($productUuid) && '' !== $productUuid) {
            return $this->productFamilyRepository->findOneBy(['productUuid' => $productUuid], $select);
        }

        return null;
    }

    private function createGroupSection(AttributeGroupInterface $group, string $locale): SectionMetadata
    {
        $translation = $group->getTranslation($locale)
            ?? (($defaultLocale = $group->getDefaultLocale()) !== null ? $group->getTranslation($defaultLocale) : null);
        $name = $translation?->getName()
            ?: $this->translator->trans('sulu_product.attributes', [], 'admin', $locale);

        $section = new SectionMetadata('attribute_group_' . $group->getId());
        $section->setLabel($name, $locale);

        return $section;
    }
}
