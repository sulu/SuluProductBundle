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

/**
 * Thrown when a submitted "attributes" list is non-empty but no entry parses into
 * {id: string, required?: bool, variantSpecific?: bool} — the shape an old client build sends looks
 * exactly like "remove every attribute", which must not happen silently.
 */
class InvalidProductFamilyAttributesException extends \Exception
{
    public function __construct()
    {
        parent::__construct(
            'The submitted "attributes" list contains no entry with a valid "id". '
            . 'This usually means the client is out of date; reload the admin and try again.',
        );
    }
}
