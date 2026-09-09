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

use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Loader\LoaderInterface;
use CmsIg\Seal\Schema\Schema;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;

/**
 * Appends the per-attribute fields to the catalogue index. Static fields win on a name clash.
 *
 * @internal
 */
final class ProductSchemaLoader implements LoaderInterface
{
    public function __construct(
        private readonly LoaderInterface $inner,
        private readonly AttributeIndexFieldProvider $attributeIndexFieldProvider,
    ) {
    }

    public function load(): Schema
    {
        $schema = $this->inner->load();
        $products = $schema->indexes[ProductIndex::NAME] ?? null;
        if (null === $products) {
            return $schema;
        }

        $indexes = $schema->indexes;
        $indexes[ProductIndex::NAME] = new Index(
            $products->name,
            \array_merge($this->attributeIndexFieldProvider->getFields(), $products->fields),
            $products->options,
        );

        return new Schema($indexes);
    }
}
