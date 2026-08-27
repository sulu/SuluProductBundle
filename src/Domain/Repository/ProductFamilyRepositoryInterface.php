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

use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\ProductFamilyInterface;

/**
 * @phpstan-type ProductFamilyRepositoryFilters array{
 *     uuid?: string,
 *     uuids?: string[],
 *     externalIdentifier?: string,
 *     productUuid?: string,
 * }
 */
interface ProductFamilyRepositoryInterface
{
    public function create(): ProductFamilyInterface;

    /**
     * @param ProductFamilyRepositoryFilters $filters
     */
    public function findOneBy(array $filters): ?ProductFamilyInterface;

    /**
     * @param ProductFamilyRepositoryFilters $filters
     *
     * @return iterable<ProductFamilyInterface>
     */
    public function findBy(array $filters = []): iterable;

    /**
     * @param ProductFamilyRepositoryFilters $filters
     *
     * @throws ProductFamilyNotFoundException
     */
    public function getOneBy(array $filters): ProductFamilyInterface;

    public function save(ProductFamilyInterface $family): void;

    public function remove(ProductFamilyInterface $family): void;
}
