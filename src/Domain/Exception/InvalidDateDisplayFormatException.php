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

class InvalidDateDisplayFormatException extends \Exception
{
    public function __construct(private string $displayFormat)
    {
        parent::__construct(\sprintf(
            'The date display format "%s" is not an ICU skeleton of date fields, e.g. yMMMM or yMMMd.',
            $displayFormat,
        ));
    }

    public function getDisplayFormat(): string
    {
        return $this->displayFormat;
    }
}
