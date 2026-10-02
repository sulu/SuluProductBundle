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

class InvalidProductAssociationException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $hint,
    ) {
        parent::__construct($message);
    }

    public function getHint(): string
    {
        return $this->hint;
    }
}
