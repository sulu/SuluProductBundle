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

namespace Sulu\Product\Application\Ai;

/**
 * One attribute filter of sulu_product_search_products_by_attributes. A value object instead of an
 * array shape so symfony/ai-platform can type the tool argument and denormalize the agent's call.
 */
final class AttributeFilter
{
    public function __construct(
        public readonly string $key,
        public readonly string $value,
    ) {
    }
}
