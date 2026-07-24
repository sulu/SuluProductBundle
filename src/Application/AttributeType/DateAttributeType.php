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

final class DateAttributeType extends AbstractAttributeType
{
    private const FORMAT = 'Y-m-d';

    public function getKey(): string
    {
        return AttributeInterface::TYPE_DATE;
    }

    public function getFormKey(): string
    {
        return 'product_attribute_date';
    }

    public function readValue(ProductAttributeValueInterface $value): mixed
    {
        return $value->getDate()?->format(self::FORMAT);
    }

    public function writeValue(ProductAttributeValueInterface $value, mixed $raw): void
    {
        if (null === $raw || '' === $raw) {
            $value->setDate(null);

            return;
        }

        Assert::string($raw);

        $date = \DateTimeImmutable::createFromFormat('!' . self::FORMAT, $raw, new \DateTimeZone('UTC'));

        Assert::isInstanceOf($date, \DateTimeImmutable::class, \sprintf('Expected a date in format "%s", got "%s".', self::FORMAT, $raw));

        $value->setDate($date);
    }
}
