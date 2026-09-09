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

namespace Sulu\Product\Infrastructure\Sulu\Search\Schema;

/**
 * Clears the cached attribute field list whenever an attribute row changes.
 *
 * @internal
 */
final class AttributeIndexFieldCacheInvalidator
{
    public function __construct(
        private readonly AttributeIndexFieldProvider $attributeIndexFieldProvider,
    ) {
    }

    public function postPersist(): void
    {
        $this->attributeIndexFieldProvider->clear();
    }

    public function postUpdate(): void
    {
        $this->attributeIndexFieldProvider->clear();
    }

    public function postRemove(): void
    {
        $this->attributeIndexFieldProvider->clear();
    }
}
