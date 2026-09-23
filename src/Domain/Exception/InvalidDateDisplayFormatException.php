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
            'The date display format "%s" renders no date. Put literal words in single quotes, e.g. \'Released\' MMMM yyyy.',
            $displayFormat,
        ));
    }

    public function getDisplayFormat(): string
    {
        return $this->displayFormat;
    }
}
