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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadataMapperRegistry;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\SchemaMetadata;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
class ProductAssociationsFormMetadataVisitor implements FormMetadataVisitorInterface
{
    private const FORM_KEY = 'product_associations';

    public function __construct(
        private readonly ProductAssociationTypeRegistry $associationTypeRegistry,
        private readonly PropertyMetadataMapperRegistry $propertyMetadataMapperRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (self::FORM_KEY !== $formMetadata->getKey()) {
            return;
        }

        $items = $formMetadata->getItems();

        /** @var PropertyMetadata[] $schemaProperties */
        $schemaProperties = [];

        $section = new SectionMetadata('associations');

        foreach ($this->associationTypeRegistry->getTypes() as $type) {
            $field = new FieldMetadata('associations/' . $type->getKey());
            $field->setType('product_selection');
            $field->setColSpan(12);
            $field->setLabel($this->translator->trans($type->getLabel(), [], 'admin', $locale), $locale);

            $section->addItem($field);

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
}
