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

class AttributeGroupNotEmptyException extends \Exception
{
    public function __construct(string $uuid, int $attributeCount, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf(
                'AttributeGroup "%s" cannot be deleted because it still contains %d attribute(s).',
                $uuid,
                $attributeCount,
            ),
            0,
            $previous,
        );
    }
}
