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

namespace Sulu\Product\Application\AttributeType;

use Sulu\Product\Domain\Model\AttributeInterface;

final class JsonAttributeType implements AttributeTypeInterface
{
    public function getKey(): string
    {
        return AttributeInterface::TYPE_JSON;
    }
}
