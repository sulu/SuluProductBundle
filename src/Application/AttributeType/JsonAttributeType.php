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

final class JsonAttributeType extends AbstractAttributeType
{
    public function getKey(): string
    {
        return AttributeInterface::TYPE_JSON;
    }

    public function getFormKey(): string
    {
        return 'product_attribute_json';
    }

    public function isAvailableInAdmin(): bool
    {
        return false;
    }
}
