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
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Webmozart\Assert\Assert;

final class TextAttributeType extends AbstractAttributeType
{
    public function getKey(): string
    {
        return AttributeInterface::TYPE_TEXT;
    }

    public function getFormKey(): string
    {
        return 'product_attribute_text';
    }

    public function readValue(array $rows): mixed
    {
        return ($rows[ProductAttributeValueInterface::DEFAULT_VALUE_KEY] ?? null)?->getText();
    }

    public function writeValue(array $rows, mixed $raw): void
    {
        $value = $rows[ProductAttributeValueInterface::DEFAULT_VALUE_KEY];

        if (null === $raw) {
            $value->setText(null);

            return;
        }

        Assert::string($raw);

        $value->setText($raw);
    }
}
