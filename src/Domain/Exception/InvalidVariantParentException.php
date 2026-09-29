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

class InvalidVariantParentException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $hint,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getHint(): string
    {
        return $this->hint;
    }
}
