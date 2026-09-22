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

class AttributeOptionKeyNotUniqueException extends \Exception
{
    public function __construct(private string $optionKey)
    {
        parent::__construct(\sprintf('The option key "%s" is used more than once.', $optionKey));
    }

    public function getOptionKey(): string
    {
        return $this->optionKey;
    }
}
