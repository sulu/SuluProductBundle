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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Measurement\Unit;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the admin field metadata for a single {@see ProductFamilyAttributeInterface}, shared between
 * {@see ProductAttributeFormMetadataVisitor} (the product's Details tab) and
 * {@see ProductVariantAttributeFormMetadataVisitor} (the variant overlay), so the field-cloning/template
 * resolution logic is not duplicated across visitors.
 *
 * @internal
 */
class AttributeFieldFactory
{
    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly FormMetadataLoaderInterface $formMetadataLoader,
        private readonly MeasurementRegistry $measurementRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{0: FieldMetadata, 1: ?FieldMetadata}|null a [field, unitField] tuple, or null when the
     *                                                         attribute's type is unknown or has no form fragment
     */
    public function build(ProductFamilyAttributeInterface $familyAttribute, string $locale): ?array
    {
        $attribute = $familyAttribute->getAttribute();
        $attributeConfig = $attribute->getConfig();
        $unitKey = $attributeConfig['unit'] ?? null;
        $unit = \is_string($unitKey) ? $this->measurementRegistry->findUnit($unitKey) : null;
        $hasUnit = $unit instanceof Unit;

        if (!$this->attributeTypeRegistry->has($attribute->getType())) {
            return null;
        }

        $type = $this->attributeTypeRegistry->get($attribute->getType());

        $template = $this->resolveTemplateField($type->getFormKey(), $locale);

        if (null === $template) {
            return null;
        }

        $translation = $attribute->getTranslation($locale)
            ?? (($defaultLocale = $attribute->getDefaultLocale()) !== null ? $attribute->getTranslation($defaultLocale) : null);

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

        $unitField = $hasUnit ? $this->buildUnitField($attribute->getId(), $unit, $locale) : null;

        return [$field, $unitField];
    }

    private function buildUnitField(int $attributeId, Unit $unit, string $locale): FieldMetadata
    {
        $field = new FieldMetadata('attributes/' . $attributeId . '_unit');
        $field->setType('single_select');
        $field->setColSpan(4);
        $field->setDisabledCondition('true');
        $field->setLabel($this->translator->trans('sulu_product.unit', [], 'admin', $locale), $locale);

        $unitKey = $unit->getKey();

        $values = new OptionMetadata();
        $values->setName('values');
        $values->setType(OptionMetadata::TYPE_COLLECTION);

        $valueOption = new OptionMetadata();
        $valueOption->setName($unitKey);
        $valueOption->setValue($unitKey);
        $valueOption->setTitle($unit->getSymbol(), $locale);
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
