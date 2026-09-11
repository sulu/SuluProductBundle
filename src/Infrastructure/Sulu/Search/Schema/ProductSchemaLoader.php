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

namespace Sulu\Product\Infrastructure\Sulu\Search\Schema;

use CmsIg\Seal\Schema\Field;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Loader\LoaderInterface;
use CmsIg\Seal\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;

/**
 * Appends one field per number and date attribute to the product object field of the website
 * index; a date is stored in the number column. Text and options attributes need none, their
 * values go into the text values field.
 *
 * @internal
 */
final class ProductSchemaLoader implements LoaderInterface
{
    private const NUMERIC_TYPES = [
        AttributeInterface::TYPE_NUMBER,
        AttributeInterface::TYPE_DATE,
    ];

    public function __construct(
        private readonly LoaderInterface $inner,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function load(): Schema
    {
        $schema = $this->inner->load();

        $index = $schema->indexes[ProductIndex::NAME] ?? null;
        $product = $index?->fields[ProductIndex::FIELD] ?? null;
        if (null === $index || !$product instanceof Field\ObjectField) {
            return $schema;
        }

        $numericValues = $product->fields[ProductIndex::NUMERIC_VALUES_FIELD] ?? null;
        if (!$numericValues instanceof Field\ObjectField) {
            return $schema;
        }

        $productFields = $product->fields;
        $productFields[ProductIndex::NUMERIC_VALUES_FIELD] = new Field\ObjectField(
            $numericValues->name,
            \array_merge($this->loadNumericFields(), $numericValues->fields),
            $numericValues->multiple,
            $numericValues->options,
        );

        $fields = $index->fields;
        $fields[ProductIndex::FIELD] = new Field\ObjectField(
            $product->name,
            $productFields,
            $product->multiple,
            $product->options,
        );

        $indexes = $schema->indexes;
        $indexes[ProductIndex::NAME] = new Index($index->name, $fields, $index->options);

        return new Schema($indexes);
    }

    /**
     * @return array<string, Field\FloatField>
     */
    private function loadNumericFields(): array
    {
        /** @var array<int, array{key: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('attribute.key AS key')
            ->from(Attribute::class, 'attribute')
            ->where('attribute.type IN (:types)')
            ->setParameter('types', self::NUMERIC_TYPES)
            ->orderBy('attribute.key', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $fields = [];
        foreach (\array_column($rows, 'key') as $attributeKey) {
            $name = ProductIndex::numericField($attributeKey);

            // A field name must start with a letter and hold word characters only.
            if (1 !== \preg_match('/^[A-Za-z]\w*$/', $name)) {
                continue;
            }

            $fields[$name] = new Field\FloatField($name, multiple: true, searchable: false, filterable: true, facet: true);
        }

        return $fields;
    }
}
