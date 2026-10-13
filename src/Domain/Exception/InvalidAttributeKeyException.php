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

class InvalidAttributeKeyException extends \Exception
{
    public function __construct(private string $key)
    {
        parent::__construct(\sprintf('The attribute key "%s" is a number. Use a key with at least one letter, e.g. "size_%s".', $key, $key));
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
