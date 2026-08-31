<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Domain\Repository;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Implementation can be found in the following class:.
 *
 * @see \Sulu\Product\Infrastructure\Doctrine\Repository\ProductRepository
 */
interface ProductRepositoryInterface
{
    /**
     * Groups are used in controllers and represents serialization / resolver group,
     * this allows that no controller need to be overwritten when something additional should be
     * loaded at that endpoint.
     */
    public const GROUP_SELECT_PRODUCT_ADMIN = 'product_admin';
    public const GROUP_SELECT_PRODUCT_WEBSITE = 'product_website';

    /**
     * Withs represents additional selects which can be load to join and select specific sub entities.
     * They are used by groups.
     */
    public const SELECT_PRODUCT_CONTENT = 'with-product-content';

    public function createNew(?string $uuid = null): ProductInterface;

    /**
     * @param array{
     *     uuid?: string,
     *     uuids?: string[],
     *     locale?: string,
     *     stage?: string,
     *     load_ghost_content?: bool,
     *     associationTargetUuid?: string,
     *     associationType?: string,
     * } $filters
     * @param array{
     *     product_admin?: bool,
     *     product_website?: bool,
     *     with-product-content?: bool|array<string, mixed>,
     * }|array<string, mixed> $selects
     *
     * @throws ProductNotFoundException
     */
    public function getOneBy(array $filters, array $selects = []): ProductInterface;

    /**
     * @param array{
     *     uuid?: string,
     *     uuids?: string[],
     *     locale?: string,
     *     stage?: string,
     *     associationTargetUuid?: string,
     *     associationType?: string,
     * } $filters
     * @param array{
     *     product_admin?: bool,
     *     product_website?: bool,
     *     with-product-content?: bool|array<string, mixed>,
     * }|array<string, mixed> $selects
     */
    public function findOneBy(array $filters, array $selects = []): ?ProductInterface;

    /**
     * @param array{
     *     code?: string,
     *     productFamilyUuid?: string,
     *     excludeUuid?: string,
     * } $filters
     *
     * Note: the `code` filter queries via dimension content (locale IS NULL), not a direct product column
     */
    public function existBy(array $filters): bool;

    /**
     * @param array{
     *     uuid?: string,
     *     uuids?: string[],
     *     locale?: string,
     *     stage?: string,
     *     categoryIds?: int[],
     *     categoryKeys?: string[],
     *     categoryOperator?: 'AND'|'OR',
     *     tagIds?: int[],
     *     tagNames?: string[],
     *     tagOperator?: 'AND'|'OR',
     *     templateKeys?: string[],
     *     associationTargetUuid?: string,
     *     associationType?: string,
     *     parent?: string,
     *     types?: string[],
     *     excludeTypes?: string[],
     *     page?: int,
     *     limit?: int,
     * } $filters
     * @param array<string, 'asc'|'desc'> $sortBy `uuid`, `position`, `created` and `changed`, plus
     *                                            `title`, `authored` and `workflowPublished` when
     *                                            `locale` and `stage` filter, which is what joins
     *                                            that table. The key order is the ORDER BY order.
     *                                            An unknown field is ignored; a dimension content
     *                                            field without those filters throws
     * @param array{
     *     product_admin?: bool,
     *     product_website?: bool,
     *     with-product-content?: bool|array<string, mixed>,
     * }|array<string, mixed> $selects
     *
     * @return iterable<ProductInterface>
     */
    public function findBy(array $filters = [], array $sortBy = [], array $selects = []): iterable;

    /**
     * @param array{
     *     uuid?: string,
     *     uuids?: string[],
     *     locale?: string,
     *     stage?: string,
     *     categoryIds?: int[],
     *     categoryKeys?: string[],
     *     categoryOperator?: 'AND'|'OR',
     *     tagIds?: int[],
     *     tagNames?: string[],
     *     tagOperator?: 'AND'|'OR',
     *     templateKeys?: string[],
     *     associationTargetUuid?: string,
     *     associationType?: string,
     *     parent?: string,
     *     types?: string[],
     *     excludeTypes?: string[],
     *     page?: int,
     *     limit?: int,
     * } $filters
     * @param array<string, 'asc'|'desc'> $sortBy `uuid`, `position`, `created` and `changed`, plus
     *                                            `title`, `authored` and `workflowPublished` when
     *                                            `locale` and `stage` filter, which is what joins
     *                                            that table. The key order is the ORDER BY order.
     *                                            An unknown field is ignored; a dimension content
     *                                            field without those filters throws
     *
     * @return iterable<string>
     */
    public function findIdentifiersBy(array $filters = [], array $sortBy = []): iterable;

    /**
     * @param array{
     *     uuid?: string,
     *     uuids?: string[],
     *     locale?: string,
     *     stage?: string,
     *     categoryIds?: int[],
     *     categoryKeys?: string[],
     *     categoryOperator?: 'AND'|'OR',
     *     tagIds?: int[],
     *     tagNames?: string[],
     *     tagOperator?: 'AND'|'OR',
     *     templateKeys?: string[],
     *     associationTargetUuid?: string,
     *     associationType?: string,
     *     parent?: string,
     *     types?: string[],
     *     excludeTypes?: string[],
     * } $filters
     */
    public function countBy(array $filters = []): int;

    /**
     * The route lives on the dimension content, so a product without one in that locale and stage
     * is absent from the result, and one whose dimension content carries no route maps to null.
     *
     * @param array{uuids: string[], locale: string, stage: string} $filters
     *
     * @return array<string, string|null> slug by product uuid
     */
    public function findSlugsBy(array $filters): array;

    public function add(ProductInterface $product): void;

    public function remove(ProductInterface $product): void;

    /** @param DimensionContentInterface<ProductInterface> $dimensionContent */
    public function removeDimensionContent(DimensionContentInterface $dimensionContent): void;
}
