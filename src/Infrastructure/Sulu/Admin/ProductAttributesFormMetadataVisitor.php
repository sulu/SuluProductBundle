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
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Fills the "product_attributes" form with one section per attribute group and one field per family
 * attribute. Validation lives in the product forms' schema, see
 * {@see ProductAttributesSchemaFormMetadataVisitor}.
 *
 * Options: "productFamily" (family uuid), so the form can be requested before the product is saved,
 * or "product" (product uuid, resolved to its family); "variant" truthy keeps only axis attributes,
 * otherwise only shared ones.
 *
 * @internal
 */
class ProductAttributesFormMetadataVisitor implements FormMetadataVisitorInterface
{
    private const FORM_KEY = 'product_attributes';

    public function __construct(
        private readonly ProductFamilyRepositoryInterface $productFamilyRepository,
        private readonly AttributeFieldFactory $attributeFieldFactory,
        private readonly TranslatorInterface $translator,
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

        // The response depends on family configuration, which changes without the URL changing.
        $formMetadata->setCacheable(false);

        $family = $this->resolveFamily($metadataOptions);
        if (null === $family) {
            return;
        }

        $variant = \filter_var($metadataOptions['variant'] ?? false, \FILTER_VALIDATE_BOOLEAN);

        /** @var array<int, SectionMetadata> $sections */
        $sections = [];

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
        }

        $items = $formMetadata->getItems();
        foreach ($sections as $section) {
            $items[$section->getName()] = $section;
        }
        $formMetadata->setItems($items);
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
