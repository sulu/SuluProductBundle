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

namespace Sulu\Product\Infrastructure\Sulu\Trash;

/**
 * What the admin needs to open a restored resource: its uuid.
 *
 * @internal
 */
final class RestoreResult
{
    public function __construct(
        private string $id,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
