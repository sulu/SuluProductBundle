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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\Validation\FieldMetadataValidatorInterface;
use Sulu\Product\Domain\Association\ProductAssociationType;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Infrastructure\Sulu\Admin\Exception\InvalidProductAssociationFieldException;
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\ProductSelectionPropertyResolver;

/**
 * @internal
 */
class ProductAssociationsFieldMetadataValidator implements FieldMetadataValidatorInterface
{
    private const FORM_KEY = 'product_associations';

    private const NAME_PREFIX = 'associations/';

    public function __construct(
        private readonly ProductAssociationTypeRegistry $associationTypeRegistry,
    ) {
    }

    public function validate(FieldMetadata $fieldMetadata, string $formKey): void
    {
        if (self::FORM_KEY !== $formKey) {
            return;
        }

        $name = $fieldMetadata->getName();
        if (!\str_starts_with($name, self::NAME_PREFIX)) {
            throw new InvalidProductAssociationFieldException($name, $formKey, 'fields in this form must be named "associations/<type>"');
        }

        $typeKey = \substr($name, \strlen(self::NAME_PREFIX));
        if (!$this->associationTypeRegistry->has($typeKey)) {
            $configuredKeys = \array_map(
                static fn (ProductAssociationType $type): string => $type->getKey(),
                $this->associationTypeRegistry->getTypes(),
            );

            throw new InvalidProductAssociationFieldException($name, $formKey, \sprintf(
                'unknown association type "%s", configured types: "%s"',
                $typeKey,
                \implode('", "', $configuredKeys),
            ));
        }

        if (ProductSelectionPropertyResolver::getType() !== $fieldMetadata->getType()) {
            throw new InvalidProductAssociationFieldException($name, $formKey, \sprintf(
                'field type "%s" is not supported, only "product_selection" fields map to product associations',
                $fieldMetadata->getType(),
            ));
        }
    }
}
