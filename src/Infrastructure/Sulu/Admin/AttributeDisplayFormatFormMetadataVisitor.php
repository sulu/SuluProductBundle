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
use Sulu\Product\Application\AttributeType\AttributeTypeInterface;
use Sulu\Product\Application\AttributeType\FormattableAttributeTypeInterface;

/**
 * Shows the display format field for the attribute types that have one, the types of a project included.
 *
 * @internal
 */
class AttributeDisplayFormatFormMetadataVisitor implements FormMetadataVisitorInterface
{
    private const FORM_KEY = 'attribute_details';

    private const FIELD_NAME = 'config/displayFormat';

    /**
     * @param iterable<AttributeTypeInterface> $attributeTypes
     */
    public function __construct(
        private readonly iterable $attributeTypes,
    ) {
    }

    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (self::FORM_KEY !== $formMetadata->getKey()) {
            return;
        }

        $field = $formMetadata->getItems()[self::FIELD_NAME] ?? null;
        if (!$field instanceof FieldMetadata) {
            return;
        }

        $conditions = [];
        foreach ($this->attributeTypes as $type) {
            if ($type instanceof FormattableAttributeTypeInterface && $type->hasDisplayFormat()) {
                // jexl only knows the escapes \" and \\, so slashes and unicode stay as they are.
                $conditions[] = 'type == ' . \json_encode($type->getKey(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            }
        }

        $field->setVisibleCondition([] === $conditions ? 'false' : \implode(' || ', $conditions));
    }
}
