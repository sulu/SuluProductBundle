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

namespace Sulu\Product\Application\Routing;

/**
 * Appends a variant to the URL of its product as `?<parameter>=<code>`.
 *
 * @internal
 */
final class VariantUrlBuilder
{
    public function __construct(
        private readonly string $parameter,
    ) {
    }

    public function append(string $productPath, string $code): string
    {
        return $productPath . '?' . $this->parameter . '=' . \rawurlencode($code);
    }
}
