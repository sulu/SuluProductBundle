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

namespace Sulu\Product\Domain\Repository;

use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;

/**
 * @phpstan-type ProductAttributeValueRepositoryFilters array{
 *     attribute?: AttributeInterface,
 *     locale?: string,
 *     stage?: string,
 * }
 */
interface ProductAttributeValueRepositoryInterface
{
    /**
     * @param ProductAttributeValueRepositoryFilters $filters
     *
     * @return list<ProductAttributeValueInterface>
     */
    public function findBy(array $filters = []): array;

    /**
     * The distinct values of the filtered attribute values, most common first, with how many
     * values share each. "value" is one representative of a group.
     *
     * @param ProductAttributeValueRepositoryFilters $filters
     *
     * @return list<array{value: ProductAttributeValueInterface, count: int}>
     */
    public function countValues(array $filters = [], int $limit = 100): array;
}
