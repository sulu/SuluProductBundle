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
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;

/**
 * Appends one field per numeric attribute to the product object field of the website index.
 *
 * @internal
 */
final class ProductSchemaLoader implements LoaderInterface
{
    public function __construct(
        private readonly LoaderInterface $inner,
        private readonly NumericAttributeLister $numericAttributeLister,
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
            \array_merge($this->numericAttributeLister->getFields(), $numericValues->fields),
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
}
