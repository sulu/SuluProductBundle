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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOptionInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Webmozart\Assert\Assert;

/**
 * Converts the value of an attribute between the admin API and its rows. The fields of the
 * attribute type's form fragment (`product_attribute_<type>`) tell which values it has: a single
 * field is the value itself, several fields make an object keyed by their names. A field is stored
 * in the row of its name, a multi select in one row per selected option, named by the option's uuid:
 * unlike the key, it never changes and has no variants a case-insensitive unique index would confuse.
 */
final class AttributeValueConverter
{
    public const TRUE = 'true';
    public const FALSE = 'false';

    public const DATE_FORMAT = 'Y-m-d';

    // The field names and types do not depend on the locale.
    private const LOCALE = 'en';

    /** @var array<string, array<string, string>> */
    private array $fields = [];

    public function __construct(
        private readonly FormMetadataLoaderInterface $formMetadataLoader,
    ) {
    }

    /**
     * The field types of the attribute type's form fragment by field name, in the fragment's order.
     *
     * @return array<string, string>
     */
    public function getFields(AttributeInterface $attribute): array
    {
        $type = $attribute->getType();

        if (isset($this->fields[$type])) {
            return $this->fields[$type];
        }

        $fragment = $this->formMetadataLoader->getMetadata('product_attribute_' . $type, self::LOCALE, []);

        $fields = [];
        if ($fragment instanceof FormMetadata) {
            foreach ($fragment->getItems() as $item) {
                if ($item instanceof FieldMetadata) {
                    $fields[$item->getName()] = $item->getType();
                }
            }
        }

        // Its rows are named by the option keys, which could not be told from the rows of other fields.
        if (\count($fields) > 1 && \in_array('select', $fields, true)) {
            throw new \LogicException(\sprintf('The form fragment of attribute type "%s" mixes a select with other fields.', $type));
        }

        return $this->fields[$type] = $fields;
    }

    /**
     * The value as the admin API and the website see it: the field's value for a single field, an
     * object of them for several, null when none is set.
     *
     * @param iterable<ProductAttributeValueInterface> $rows the rows of the attribute, possibly none
     */
    public function read(AttributeInterface $attribute, iterable $rows): mixed
    {
        $rowsByKey = [];
        foreach ($rows as $row) {
            $rowsByKey[$row->getValueKey()] = $row;
        }

        $fields = $this->getFields($attribute);

        if (1 === \count($fields)) {
            return $this->readField($attribute, \array_key_first($fields), \current($fields), $rowsByKey);
        }

        $values = [];
        foreach ($fields as $name => $type) {
            $values[$name] = $this->readField($attribute, $name, $type, $rowsByKey);
        }

        return [] === \array_filter($values, static fn (mixed $value): bool => null !== $value) ? null : $values;
    }

    /**
     * The value of every attribute the rows belong to, by attribute uuid.
     *
     * @param iterable<ProductAttributeValueInterface> $rows
     *
     * @return array<string, array{attribute: AttributeInterface, value: mixed}>
     */
    public function readAll(iterable $rows): array
    {
        /** @var array<string, array{attribute: AttributeInterface, rows: list<ProductAttributeValueInterface>}> $rowsByAttribute */
        $rowsByAttribute = [];
        foreach ($rows as $row) {
            $attribute = $row->getAttribute();
            $rowsByAttribute[$attribute->getUuid()]['attribute'] = $attribute;
            $rowsByAttribute[$attribute->getUuid()]['rows'][] = $row;
        }

        $values = [];
        foreach ($rowsByAttribute as $uuid => ['attribute' => $attribute, 'rows' => $attributeRows]) {
            $values[$uuid] = ['attribute' => $attribute, 'value' => $this->read($attribute, $attributeRows)];
        }

        return $values;
    }

    /**
     * The rows the value is stored in by value key, none for an empty value. Validates the whole
     * value, so a rejected value changes nothing.
     *
     * @return array<string, AttributeRowData>
     */
    public function write(AttributeInterface $attribute, mixed $raw): array
    {
        $fields = $this->getFields($attribute);
        Assert::notEmpty($fields, \sprintf('Attribute type "%s" has no form fragment.', $attribute->getType()));

        if (1 === \count($fields)) {
            return $this->writeField($attribute, \array_key_first($fields), \current($fields), $raw);
        }

        if (null === $raw || '' === $raw) {
            return [];
        }

        Assert::isArray($raw, \sprintf('Expected an object for attribute "%s".', $attribute->getKey()));
        $unknown = \array_diff(\array_keys($raw), \array_keys($fields));
        Assert::isEmpty($unknown, \sprintf('Attribute "%s" has no field "%s".', $attribute->getKey(), \implode('", "', $unknown)));

        $rows = [];
        foreach ($fields as $name => $type) {
            $rows += $this->writeField($attribute, $name, $type, $raw[$name] ?? null);
        }

        return $rows;
    }

    /**
     * @param array<string, ProductAttributeValueInterface> $rows by value key
     */
    private function readField(AttributeInterface $attribute, string $name, string $type, array $rows): mixed
    {
        if ('select' === $type) {
            // A deleted option leaves its row without an option.
            $optionKeys = \array_values(\array_filter(
                \array_map(static fn (ProductAttributeValueInterface $row): ?string => $row->getAttributeOptionKey(), $rows),
                static fn (?string $optionKey): bool => null !== $optionKey,
            ));

            if ([] === $optionKeys) {
                return null;
            }

            // The rows come in no order, the attribute's options have one.
            $order = [];
            foreach ($attribute->getOptions() as $option) {
                $order[] = $option->getKey();
            }
            \usort($optionKeys, static fn (string $a, string $b): int => \array_search($a, $order, true) <=> \array_search($b, $order, true));

            return $optionKeys;
        }

        $row = $rows[$name] ?? null;
        if (null === $row) {
            return null;
        }

        return match ($type) {
            'number' => $row->getNumber(),
            'date' => null === $row->getNumber() ? null : (new \DateTimeImmutable('@' . (int) $row->getNumber()))->format(self::DATE_FORMAT),
            'text_line' => $row->getText(),
            'single_select' => AttributeInterface::TYPE_BOOLEAN === $attribute->getType()
                ? $this->readBoolean($row->getNumber())
                : $row->getAttributeOptionKey(),
            default => throw $this->unsupported($attribute, $type),
        };
    }

    /**
     * @return array<string, AttributeRowData>
     */
    private function writeField(AttributeInterface $attribute, string $name, string $type, mixed $raw): array
    {
        if ('select' === $type) {
            return $this->writeOptions($attribute, $raw);
        }

        if (null === $raw || '' === $raw) {
            return [];
        }

        return [$name => match ($type) {
            'number' => new AttributeRowData(number: $this->toNumber($raw)),
            'date' => new AttributeRowData(number: $this->toTimestamp($raw)),
            'text_line' => new AttributeRowData(text: $this->toText($raw)),
            'single_select' => AttributeInterface::TYPE_BOOLEAN === $attribute->getType()
                ? new AttributeRowData(number: $this->toBooleanNumber($raw))
                : new AttributeRowData(option: $this->toOption($attribute, $raw)),
            default => throw $this->unsupported($attribute, $type),
        }];
    }

    /**
     * @return array<string, AttributeRowData>
     */
    private function writeOptions(AttributeInterface $attribute, mixed $raw): array
    {
        if (null === $raw || '' === $raw || [] === $raw) {
            return [];
        }

        Assert::isList($raw, \sprintf('Expected a list of options for attribute "%s".', $attribute->getKey()));

        $rows = [];
        foreach ($raw as $optionKey) {
            $option = $this->toOption($attribute, $optionKey);
            $rows[$option->getUuid()] = new AttributeRowData(option: $option);
        }

        return $rows;
    }

    private function toNumber(mixed $raw): float
    {
        Assert::numeric($raw);

        return (float) $raw;
    }

    private function toText(mixed $raw): string
    {
        Assert::string($raw);

        return $raw;
    }

    private function toTimestamp(mixed $raw): float
    {
        Assert::string($raw);

        $date = \DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $raw, new \DateTimeZone('UTC'));

        Assert::isInstanceOf($date, \DateTimeImmutable::class, \sprintf('Expected a date in format "%s", got "%s".', self::DATE_FORMAT, $raw));
        Assert::same($date->format(self::DATE_FORMAT), $raw, \sprintf('Expected a valid date in format "%s", got "%s".', self::DATE_FORMAT, $raw));

        return (float) $date->getTimestamp();
    }

    private function toOption(AttributeInterface $attribute, mixed $raw): AttributeOptionInterface
    {
        Assert::string($raw);

        $option = $attribute->getOption($raw);
        Assert::notNull($option, \sprintf('Attribute "%s" has no option "%s".', $attribute->getKey(), $raw));

        return $option;
    }

    /**
     * Read as "true" and "false", because the admin's single_select only takes string values;
     * written from those or a real bool.
     */
    private function toBooleanNumber(mixed $raw): float
    {
        if (\is_string($raw)) {
            Assert::oneOf($raw, [self::TRUE, self::FALSE]);
            $raw = self::TRUE === $raw;
        }

        Assert::boolean($raw);

        return $raw ? 1.0 : 0.0;
    }

    private function readBoolean(?float $number): ?string
    {
        if (null === $number) {
            return null;
        }

        return 0.0 !== $number ? self::TRUE : self::FALSE;
    }

    private function unsupported(AttributeInterface $attribute, string $type): \LogicException
    {
        return new \LogicException(\sprintf('The form field type "%s" of attribute type "%s" has no value mapping.', $type, $attribute->getType()));
    }
}
