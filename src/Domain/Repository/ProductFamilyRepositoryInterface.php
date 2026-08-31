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
 * @phpstan-type ProductFamilyRepositorySelects array{
 *     product_family_form?: bool,
 *     with-family-attributes?: bool,
 *     with-family-attribute-groups?: bool,
 *     with-family-attribute-options?: bool,
 * }|array<string, mixed>
 */
interface ProductFamilyRepositoryInterface
{
    /**
     * Groups represents serialization / resolver group, this one is used by the form metadata visitors.
     */
    public const GROUP_SELECT_PRODUCT_FAMILY_FORM = 'product_family_form';

    /**
     * Withs represents additional selects which can be load to join and select specific sub entities.
     * They are used by groups.
     */
    public const SELECT_FAMILY_ATTRIBUTES = 'with-family-attributes';
    public const SELECT_FAMILY_ATTRIBUTE_GROUPS = 'with-family-attribute-groups';
    public const SELECT_FAMILY_ATTRIBUTE_OPTIONS = 'with-family-attribute-options';

    public function create(): ProductFamilyInterface;

    /**
     * @param ProductFamilyRepositoryFilters $filters
     * @param ProductFamilyRepositorySelects $selects
     */
    public function findOneBy(array $filters, array $selects = []): ?ProductFamilyInterface;

    /**
     * @param ProductFamilyRepositoryFilters $filters
     * @param ProductFamilyRepositorySelects $selects
     *
     * @return iterable<ProductFamilyInterface>
     */
    public function findBy(array $filters = [], array $selects = []): iterable;

    /**
     * @param ProductFamilyRepositoryFilters $filters
     * @param ProductFamilyRepositorySelects $selects
     *
     * @throws ProductFamilyNotFoundException
     */
    public function getOneBy(array $filters, array $selects = []): ProductFamilyInterface;

    public function save(ProductFamilyInterface $family): void;

    public function remove(ProductFamilyInterface $family): void;
}
