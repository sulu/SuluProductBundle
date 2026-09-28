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
use Webmozart\Assert\Assert;

/**
 * Two numbers, "from" and "to", in a row each. Either may be empty, for a range open on one side.
 */
final class RangeAttributeType extends AbstractAttributeType
{
    private const FROM = 'from';
    private const TO = 'to';

    public function getKey(): string
    {
        return AttributeInterface::TYPE_RANGE;
    }

    public function getFormKey(): string
    {
        return 'product_attribute_range';
    }

    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void
    {
        $this->addNumberOptions($field, $attribute);
    }

    public function getValueKeys(AttributeInterface $attribute, mixed $raw): array
    {
        return [self::FROM, self::TO];
    }

    /**
     * @return array{from: float|null, to: float|null}|null
     */
    public function readValue(array $rows): ?array
    {
        $from = ($rows[self::FROM] ?? null)?->getNumber();
        $to = ($rows[self::TO] ?? null)?->getNumber();

        if (null === $from && null === $to) {
            return null;
        }

        return [self::FROM => $from, self::TO => $to];
    }

    public function writeValue(array $rows, mixed $raw): void
    {
        $attributeKey = $rows[self::FROM]->getAttributeKey();

        Assert::isArray($raw, \sprintf('Expected "from" and "to" for range "%s".', $attributeKey));

        $from = $this->readBound($raw, self::FROM, $attributeKey);
        $to = $this->readBound($raw, self::TO, $attributeKey);

        Assert::false(null === $from && null === $to, \sprintf('Expected "from" or "to" for range "%s".', $attributeKey));

        if (null !== $from && null !== $to) {
            Assert::lessThanEq($from, $to, \sprintf('Expected "from" not to exceed "to" for range "%s".', $attributeKey));
        }

        $rows[self::FROM]->setNumber($from);
        $rows[self::TO]->setNumber($to);
    }

    /**
     * @param array<mixed> $raw
     */
    private function readBound(array $raw, string $bound, string $attributeKey): ?float
    {
        $value = $raw[$bound] ?? null;
        if (null === $value || '' === $value) {
            return null;
        }

        Assert::numeric($value, \sprintf('Expected a numeric "%s" for range "%s".', $bound, $attributeKey));

        return (float) $value;
    }
}
