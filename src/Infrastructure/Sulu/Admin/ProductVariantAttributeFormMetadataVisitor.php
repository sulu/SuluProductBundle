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
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Injects the parent product family's `variant=true` (axis) attributes into the variant overlay
 * form (`product_variant`), grouped into a section per attribute group; non-variant (shared)
 * attributes are never injected.
 *
 * @internal
 */
class ProductVariantAttributeFormMetadataVisitor implements FormMetadataVisitorInterface
{
    private const FORM_KEY = 'product_variant';

    public function __construct(
        private readonly ProductFamilyRepositoryInterface $productFamilyRepository,
        private readonly AttributeFieldFactory $attributeFieldFactory,
        private readonly PropertyMetadataMapperRegistry $propertyMetadataMapperRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (self::FORM_KEY !== $formMetadata->getKey()) {
            return;
        }

        $parentId = $metadataOptions['parentId'] ?? null;
        if (!\is_string($parentId)) {
            return;
        }

        $family = $this->productFamilyRepository->findOneBy(['productUuid' => $parentId]);

        if (null === $family) {
            return;
        }

        $items = $formMetadata->getItems();

        /** @var array<int, SectionMetadata> $sections */
        $sections = [];
        /** @var PropertyMetadata[] $schemaProperties */
        $schemaProperties = [];

        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            if (!$familyAttribute->isVariantSpecific()) {
                continue;
            }

            $result = $this->attributeFieldFactory->build($familyAttribute, $locale);
            if (null === $result) {
                continue;
            }
            [$field, $unitField] = $result;

            $group = $familyAttribute->getAttribute()->getGroup();
            $groupId = $group->getId();

            if (!isset($sections[$groupId])) {
                $sections[$groupId] = $this->createGroupSection($group, $locale);
            }
            $section = $sections[$groupId];

            $section->addItem($field);

            if (null !== $unitField) {
                $section->addItem($unitField);
            }

            $schemaProperties[] = $this->propertyMetadataMapperRegistry->has($field->getType())
                ? $this->propertyMetadataMapperRegistry->get($field->getType())->mapPropertyMetadata($field)
                : new PropertyMetadata($field->getName(), $field->isRequired());
        }

        if ([] !== $sections) {
            foreach ($sections as $section) {
                $items[$section->getName()] = $section;
            }
            $formMetadata->setItems($items);
        }

        if ([] !== $schemaProperties) {
            $formMetadata->setSchema($formMetadata->getSchema()->merge(new SchemaMetadata($schemaProperties)));
        }

        $formMetadata->setCacheable(false);
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
