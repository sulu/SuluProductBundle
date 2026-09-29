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

final class TextAttributeType extends AbstractSingleRowAttributeType
{
    public function getKey(): string
    {
        return AttributeInterface::TYPE_TEXT;
    }

    public function getFormKey(): string
    {
        return 'product_attribute_text';
    }

    protected function readRow(ProductAttributeValueInterface $row): mixed
    {
        return $row->getText();
    }

    protected function writeRow(ProductAttributeValueInterface $row, mixed $raw): void
    {
        if (null === $raw) {
            $row->setText(null);

            return;
        }

        Assert::string($raw);

        $row->setText($raw);
    }
}
