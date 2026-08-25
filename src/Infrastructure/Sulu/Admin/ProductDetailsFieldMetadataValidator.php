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
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\Exception\InvalidProductDetailsFieldException;

/**
 * Rejects `details/<field>` names that would shadow a fixed key of the root `product` namespace.
 *
 * @internal
 */
class ProductDetailsFieldMetadataValidator implements FieldMetadataValidatorInterface
{
    private const NAME_PREFIX = 'details/';

    private const RESERVED_NAMES = [
        'attributes', 'associations', 'variants',
        'code', 'externalIdentifier', 'productFamily', 'status',
    ];

    public function validate(FieldMetadata $fieldMetadata, string $formKey): void
    {
        if (ProductInterface::FORM_KEY !== $formKey) {
            return;
        }

        $name = $fieldMetadata->getName();
        if (!\str_starts_with($name, self::NAME_PREFIX)) {
            return;
        }

        $field = \substr($name, \strlen(self::NAME_PREFIX));
        if (!\in_array($field, self::RESERVED_NAMES, true)) {
            return;
        }

        throw new InvalidProductDetailsFieldException($name, $formKey, \sprintf(
            'the name "%s" is reserved by the product namespace, reserved names: "%s"',
            $field,
            \implode('", "', self::RESERVED_NAMES),
        ));
    }
}
