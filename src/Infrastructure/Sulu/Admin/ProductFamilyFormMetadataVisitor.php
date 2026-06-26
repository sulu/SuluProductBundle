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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataVisitorInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
class ProductFamilyFormMetadataVisitor implements FormMetadataVisitorInterface
{
    public function __construct(
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (ProductFamilyInterface::FORM_KEY !== $formMetadata->getKey()) {
            return;
        }

        $items = $formMetadata->getItems();

        foreach ($this->attributeGroupRepository->findAll() as $group) {
            $groupTranslation = $group->getTranslation($locale)
                ?? (($dl = $group->getDefaultLocale()) !== null ? $group->getTranslation($dl) : null);
            $groupName = $groupTranslation?->getName() ?? '';

            $section = new SectionMetadata('attribute_group_' . $group->getId());
            $section->setLabel($groupName, $locale);

            foreach ($group->getGroupAttributes() as $groupAttribute) {
                $attribute = $groupAttribute->getAttribute();
                $attributeId = $attribute->getId();
                $attributeTranslation = $attribute->getTranslation($locale)
                    ?? (($dl = $attribute->getDefaultLocale()) !== null ? $attribute->getTranslation($dl) : null);
                $attributeName = $attributeTranslation?->getName() ?? $attribute->getKey();

                $enabledField = new FieldMetadata('attributes/' . $attributeId . '/enabled');
                $enabledField->setType('checkbox');
                $enabledField->setLabel($this->translator->trans('sulu_product.attribute_enabled', ['%attributeName%' => $attributeName], 'admin', $locale), $locale);
                $enabledField->setColSpan(6);
                $enabledField->addOption($this->createTogglerOption());
                $section->addItem($enabledField);

                $requiredField = new FieldMetadata('attributes/' . $attributeId . '/required');
                $requiredField->setType('checkbox');
                $requiredField->setLabel($this->translator->trans('sulu_product.attribute_required', ['%attributeName%' => $attributeName], 'admin', $locale), $locale);
                $requiredField->setColSpan(6);
                $requiredField->setDisabledCondition('!attributes["' . $attributeId . '"].enabled');
                $requiredField->addOption($this->createTogglerOption());
                $section->addItem($requiredField);
            }

            $items[$section->getName()] = $section;
        }

        $formMetadata->setItems($items);
        $formMetadata->setCacheable(false);
    }

    private function createTogglerOption(): OptionMetadata
    {
        $option = new OptionMetadata();
        $option->setName('type');
        $option->setValue('toggler');

        return $option;
    }
}
