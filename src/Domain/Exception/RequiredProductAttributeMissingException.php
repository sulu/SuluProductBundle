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

namespace Sulu\Product\Domain\Exception;

class RequiredProductAttributeMissingException extends \Exception
{
    public function __construct(
        private readonly string $attributeKey,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            \sprintf('The required product attribute "%s" is missing a value.', $attributeKey),
            0,
            $previous,
        );
    }

    public function getAttributeKey(): string
    {
        return $this->attributeKey;
    }
}
