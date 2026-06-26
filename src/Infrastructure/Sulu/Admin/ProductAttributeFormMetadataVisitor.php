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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataVisitorInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapperRegistry;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\SchemaMetadata;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Measurement\MeasurementFamilyRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
class ProductAttributeFormMetadataVisitor implements FormMetadataVisitorInterface
{
    private const FORM_KEY = 'product_details';

    public function __construct(
        private readonly ProductFamilyRepositoryInterface $productFamilyRepository,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly FormMetadataLoaderInterface $formMetadataLoader,
        private readonly PropertyMetadataMapperRegistry $propertyMetadataMapperRegistry,
        private readonly MeasurementFamilyRegistry $measurementFamilyRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (self::FORM_KEY !== $formMetadata->getKey()) {
            return;
        }

        $id = $metadataOptions['id'] ?? null;
        if (!\is_string($id)) {
            return;
        }

        $family = $this->productFamilyRepository->findOneBy(['productUuid' => $id]);

        if (null === $family) {
            return;
        }

        $items = $formMetadata->getItems();

        /** @var PropertyMetadata[] $schemaProperties */
        $schemaProperties = [];

        $section = new SectionMetadata('attributes');
        $section->setLabel($this->translator->trans('sulu_product.attributes', [], 'admin', $locale), $locale);

        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            $attribute = $familyAttribute->getAttribute();
            $attributeConfig = $attribute->getConfig();
            $measurementFamily = $attributeConfig['measurementFamily'] ?? null;
            $unit = $attributeConfig['unit'] ?? null;
            $hasUnit = \is_string($measurementFamily) && \is_string($unit);

            if (!$this->attributeTypeRegistry->has($attribute->getType())) {
                continue;
            }

            $type = $this->attributeTypeRegistry->get($attribute->getType());

            $template = $this->resolveTemplateField($type->getFormKey(), $locale);

            if (null === $template) {
                continue;
            }

            $translation = $attribute->getTranslation($locale)
                ?? (($dl = $attribute->getDefaultLocale()) !== null ? $attribute->getTranslation($dl) : null);

            $field = $this->cloneFieldWithName($template, 'attributes/' . $attribute->getId());
            $field->setLabel($translation?->getName() ?? $attribute->getKey(), $locale);
            $field->setRequired($familyAttribute->isRequired());

            if ($hasUnit) {
                $field->setColSpan(8);
            }

            $description = $translation?->getDescription();
            if (null !== $description) {
                $field->setDescription(\strip_tags($description), $locale);
            }

            $type->configureField($field, $attribute, $locale);

            $section->addItem($field);

            if ($hasUnit) {
                $unitField = $this->buildUnitField($attribute->getId(), $measurementFamily, $unit, $locale);
                $section->addItem($unitField);
            }

            $schemaProperties[] = $this->propertyMetadataMapperRegistry->has($field->getType())
                ? $this->propertyMetadataMapperRegistry->get($field->getType())->mapPropertyMetadata($field)
                : new PropertyMetadata($field->getName(), $field->isRequired());
        }

        if ([] !== $section->getItems()) {
            $items[$section->getName()] = $section;
            $formMetadata->setItems($items);
        }

        if ([] !== $schemaProperties) {
            $formMetadata->setSchema($formMetadata->getSchema()->merge(new SchemaMetadata($schemaProperties)));
        }

        $formMetadata->setCacheable(false);
    }

    private function buildUnitField(int $attributeId, string $measurementFamily, string $unit, string $locale): FieldMetadata
    {
        $field = new FieldMetadata('attributes/' . $attributeId . '_unit');
        $field->setType('single_select');
        $field->setColSpan(4);
        $field->setDisabledCondition('true');
        $field->setLabel($this->translator->trans('sulu_product.unit', [], 'admin', $locale), $locale);

        $symbol = $this->measurementFamilyRegistry->getUnits($measurementFamily)[$unit] ?? $unit;

        $values = new OptionMetadata();
        $values->setName('values');
        $values->setType(OptionMetadata::TYPE_COLLECTION);

        $valueOption = new OptionMetadata();
        $valueOption->setName($unit);
        $valueOption->setValue($unit);
        $valueOption->setTitle($symbol, $locale);
        $values->addValueOption($valueOption);

        $field->addOption($values);

        return $field;
    }

    private function resolveTemplateField(string $formKey, string $locale): ?FieldMetadata
    {
        $fragment = $this->formMetadataLoader->getMetadata($formKey, $locale, []);
        if (!$fragment instanceof FormMetadata) {
            return null;
        }

        foreach ($fragment->getItems() as $item) {
            if ($item instanceof FieldMetadata && 'value' === $item->getName()) {
                return $item;
            }
        }

        return null;
    }

    private function cloneFieldWithName(FieldMetadata $template, string $name): FieldMetadata
    {
        $field = new FieldMetadata($name);
        $field->setType($template->getType());
        $field->setColSpan($template->getColSpan());
        $field->setDefaultType($template->getDefaultType());
        $field->setVisibleCondition($template->getVisibleCondition());
        $field->setDisabledCondition($template->getDisabledCondition());
        $field->setMinOccurs($template->getMinOccurs());
        $field->setMaxOccurs($template->getMaxOccurs());
        $field->setSpaceAfter($template->getSpaceAfter());
        $field->setOnInvalid($template->getOnInvalid());
        $field->setTags($template->getTags());

        foreach ($template->getOptions() as $option) {
            $field->addOption($option);
        }

        foreach ($template->getTypes() as $blockType) {
            $field->addType($blockType);
        }

        $field->setDescriptions($template->getDescriptions());

        return $field;
    }
}
