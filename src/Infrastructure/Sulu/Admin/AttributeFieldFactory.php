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
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;

/**
 * Builds the admin field metadata for a single {@see ProductFamilyAttributeInterface}, so the
 * fragment-cloning logic lives in one place.
 *
 * @internal
 */
class AttributeFieldFactory
{
    // Mirrored by the admin's ProductAttributesRenderer (sulu/sulu, the JS contract), so it is public.
    public const NAME_PREFIX = 'attribute_';

    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly FormMetadataLoaderInterface $formMetadataLoader,
        private readonly MeasurementRegistry $measurementRegistry,
    ) {
    }

    /**
     * @return FieldMetadata|null null when the attribute's type is unknown or has no form fragment
     */
    public function build(ProductFamilyAttributeInterface $familyAttribute, string $locale): ?FieldMetadata
    {
        $attribute = $familyAttribute->getAttribute();

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

        $field = $this->cloneFieldWithName($template, self::NAME_PREFIX . $attribute->getId());
        $field->setLabel($this->buildLabel($translation?->getName() ?? $attribute->getKey(), $attribute->getConfig()), $locale);
        $field->setRequired($familyAttribute->isRequired());

        $description = $translation?->getDescription();
        if (null !== $description) {
            $field->setDescription(\strip_tags($description), $locale);
        }

        $type->configureField($field, $attribute, $locale);

        return $field;
    }

    /**
     * The unit is part of the label, "Length (mm)", so the row needs no unit column.
     *
     * @param array<string, mixed> $config
     */
    private function buildLabel(string $name, array $config): string
    {
        $unitKey = $config['unit'] ?? null;
        $unit = \is_string($unitKey) ? $this->measurementRegistry->findUnit($unitKey) : null;

        if (null === $unit) {
            return $name;
        }

        return $name . ' (' . $unit->getSymbol() . ')';
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
