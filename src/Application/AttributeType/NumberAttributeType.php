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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Webmozart\Assert\Assert;

final class NumberAttributeType extends AbstractSingleRowAttributeType
{
    public function getKey(): string
    {
        return AttributeInterface::TYPE_NUMBER;
    }

    public function getFormKey(): string
    {
        return 'product_attribute_number';
    }

    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void
    {
        $this->addNumberOptions($field, $attribute);
    }

    protected function readRow(ProductAttributeValueInterface $row): mixed
    {
        return $row->getNumber();
    }

    protected function writeRow(ProductAttributeValueInterface $row, mixed $raw): void
    {
        if (null === $raw || '' === $raw) {
            $row->setNumber(null);

            return;
        }

        Assert::numeric($raw);

        $row->setNumber((float) $raw);
    }
}
