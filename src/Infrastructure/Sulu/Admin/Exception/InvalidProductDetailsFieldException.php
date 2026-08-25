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

namespace Sulu\Product\Infrastructure\Sulu\Admin\Exception;

use Sulu\Component\Content\Exception\InvalidFieldMetadataException;

class InvalidProductDetailsFieldException extends InvalidFieldMetadataException
{
    public function __construct(private readonly string $propertyName, string $formKey, string $reason)
    {
        parent::__construct(
            $formKey,
            \sprintf('Property "%s" in form "%s" is invalid: %s', $this->propertyName, $formKey, $reason),
        );
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }
}
