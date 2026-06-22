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
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;

/**
 * @internal
 */
class ProductAttributeFormMetadataVisitor implements FormMetadataVisitorInterface
{
    private const FORM_KEY = 'product_attributes';

    public function __construct(
        private readonly ProductFamilyRepositoryInterface $productFamilyRepository,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly FormMetadataLoaderInterface $formMetadataLoader,
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

        $family = $this->productFamilyRepository->getOneBy(['productUuid' => $id]);
        $items = $formMetadata->getItems();

        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            $attribute = $familyAttribute->getAttribute();

            if (!$this->attributeTypeRegistry->has($attribute->getType())) {
                continue;
            }

            $type = $this->attributeTypeRegistry->get($attribute->getType());

            $template = $this->resolveTemplateField($type->getFormKey(), $locale);

            if (null === $template) {
                continue;
            }

            $translation = $attribute->getTranslation($locale);

            $field = $this->cloneFieldWithName($template, 'attributes/' . $attribute->getId());
            $field->setLabel($translation?->getName() ?? $attribute->getKey(), $locale);
            $field->setRequired($familyAttribute->isRequired());

            $description = $translation?->getDescription();
            if (null !== $description) {
                $field->setDescription($description, $locale);
            }

            $type->configureField($field, $attribute, $locale);

            $items[$field->getName()] = $field;
        }

        $formMetadata->setItems($items);
        $formMetadata->setCacheable(false);
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
