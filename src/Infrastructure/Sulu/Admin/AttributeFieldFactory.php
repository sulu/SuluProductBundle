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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\ItemMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\AnyOfsMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\NullMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\ObjectMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapperRegistry;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslationInterface;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;

/**
 * Builds the admin field metadata for a single {@see ProductFamilyAttributeInterface}.
 *
 * @internal
 */
class AttributeFieldFactory
{
    // The admin's ProductAttributes container strips this prefix to recover the attribute uuid.
    private const NAME_PREFIX = 'attribute_';

    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly FormMetadataLoaderInterface $formMetadataLoader,
        private readonly MeasurementRegistry $measurementRegistry,
        private readonly PropertyMetadataMapperRegistry $propertyMetadataMapperRegistry,
    ) {
    }

    /**
     * The JSON schema of the attribute's value, keyed by attribute uuid like the submitted values.
     * Several fields make an object, none of them required: the data mapper asks for at least one value.
     *
     * @return PropertyMetadata|null null when the attribute has no field
     */
    public function buildSchemaProperty(ProductFamilyAttributeInterface $familyAttribute, string $locale): ?PropertyMetadata
    {
        $uuid = $familyAttribute->getAttribute()->getUuid();
        $fields = $this->buildFields($familyAttribute, $locale, $uuid);

        if ([] === $fields) {
            return null;
        }

        if (1 === \count($fields)) {
            return $this->mapField($fields[0]);
        }

        $properties = [];
        foreach ($fields as $field) {
            $field->setRequired(false);
            $properties[] = $this->mapField($field);
        }

        $object = new ObjectMetadata($properties);
        $required = $familyAttribute->isRequired();

        return new PropertyMetadata($uuid, $required, $required ? $object : new AnyOfsMetadata([new NullMetadata(), $object]));
    }

    /**
     * A section of the fields when the form fragment has several.
     *
     * @return FieldMetadata|SectionMetadata|null null when the attribute's type has no form fragment
     */
    public function build(ProductFamilyAttributeInterface $familyAttribute, string $locale): FieldMetadata|SectionMetadata|null
    {
        $name = self::NAME_PREFIX . $familyAttribute->getAttribute()->getUuid();
        $fields = $this->buildFields($familyAttribute, $locale, $name);

        if (\count($fields) <= 1) {
            return $fields[0] ?? null;
        }

        $attribute = $familyAttribute->getAttribute();
        $section = new SectionMetadata($name);
        $section->setLabel($this->buildLabel($this->getName($attribute, $locale), $attribute->getConfig()), $locale);

        foreach ($fields as $field) {
            $section->addItem($field);
        }

        return $section;
    }

    /**
     * A single field is named and labelled after the attribute, several keep the fragment's names and labels.
     *
     * @return list<FieldMetadata>
     */
    private function buildFields(
        ProductFamilyAttributeInterface $familyAttribute,
        string $locale,
        string $name,
    ): array {
        $attribute = $familyAttribute->getAttribute();

        if (!$this->attributeTypeRegistry->has($attribute->getType())) {
            return [];
        }

        $type = $this->attributeTypeRegistry->get($attribute->getType());
        $templates = $this->resolveTemplateFields('product_attribute_' . $attribute->getType(), $locale);
        $single = 1 === \count($templates);

        $fields = [];
        foreach ($templates as $template) {
            $field = $this->cloneFieldWithName($template, $single ? $name : $template->getName());
            $field->setRequired($familyAttribute->isRequired());

            if ($single) {
                $field->setLabel($this->buildLabel($this->getName($attribute, $locale), $attribute->getConfig()), $locale);

                $description = $this->getTranslation($attribute, $locale)?->getDescription();
                if (null !== $description) {
                    $field->setDescription(\strip_tags($description), $locale);
                }
            } elseif (null !== $label = $template->getLabel($locale)) {
                $field->setLabel($label, $locale);
            }

            $fields[] = $field;
        }

        $form = new FormMetadata();
        $form->setKey('product_attribute_' . $attribute->getType());
        foreach ($fields as $field) {
            $form->addItem($field);
        }

        $type->configureForm($form, $attribute, $locale);

        return $fields;
    }

    private function mapField(FieldMetadata $field): PropertyMetadata
    {
        return $this->propertyMetadataMapperRegistry->has($field->getType())
            ? $this->propertyMetadataMapperRegistry->get($field->getType())->mapPropertyMetadata($field)
            : new PropertyMetadata($field->getName(), $field->isRequired());
    }

    private function getTranslation(AttributeInterface $attribute, string $locale): ?AttributeTranslationInterface
    {
        return $attribute->getTranslation($locale)
            ?? (($defaultLocale = $attribute->getDefaultLocale()) !== null ? $attribute->getTranslation($defaultLocale) : null);
    }

    private function getName(AttributeInterface $attribute, string $locale): string
    {
        return $this->getTranslation($attribute, $locale)?->getName() ?? $attribute->getKey();
    }

    /**
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

    /**
     * @return list<FieldMetadata>
     */
    private function resolveTemplateFields(string $formKey, string $locale): array
    {
        $fragment = $this->formMetadataLoader->getMetadata($formKey, $locale, []);
        if (!$fragment instanceof FormMetadata) {
            return [];
        }

        return \array_values(\array_filter(
            $fragment->getItems(),
            static fn (ItemMetadata $item): bool => $item instanceof FieldMetadata,
        ));
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
