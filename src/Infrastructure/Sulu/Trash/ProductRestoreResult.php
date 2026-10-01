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
 * What the admin needs to open the restored product: the product to edit and a locale it has.
 *
 * @internal
 */
final class ProductRestoreResult
{
    public function __construct(
        private string $id,
        private string $locale,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
