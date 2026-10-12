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

namespace Sulu\Product\Application\Attribute;

use Sulu\Product\Domain\Model\AttributeOptionInterface;

/**
 * What one row of an attribute value stores: the columns a field of the attribute's form is written to.
 */
final class AttributeRowData
{
    public function __construct(
        public readonly ?float $number = null,
        public readonly ?string $text = null,
        public readonly ?AttributeOptionInterface $option = null,
    ) {
    }
}
