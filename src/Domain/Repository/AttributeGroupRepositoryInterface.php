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

use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Model\AttributeGroupInterface;

/**
 * @phpstan-type AttributeGroupRepositoryFilters array{
 *     uuid?: string,
 *     externalIdentifier?: string,
 * }
 * @phpstan-type AttributeGroupRepositorySortBy array{
 *     id?: 'asc'|'desc',
 *     externalIdentifier?: 'asc'|'desc',
 * }
 * @phpstan-type AttributeGroupRepositorySelects array{
 *     product_family_form?: bool,
 *     with-group-attributes?: bool,
 *     with-group-attribute-translations?: bool,
 *     with-group-translations?: bool,
 * }|array<string, mixed>
 */
interface AttributeGroupRepositoryInterface
{
    public const GROUP_SELECT_PRODUCT_FAMILY_FORM = 'product_family_form';

    public const SELECT_GROUP_ATTRIBUTES = 'with-group-attributes';
    public const SELECT_GROUP_ATTRIBUTE_TRANSLATIONS = 'with-group-attribute-translations';
    public const SELECT_GROUP_TRANSLATIONS = 'with-group-translations';

    public function create(): AttributeGroupInterface;

    /**
     * @param AttributeGroupRepositoryFilters $filters
     */
    public function findOneBy(array $filters): ?AttributeGroupInterface;

    /**
     * @param AttributeGroupRepositoryFilters $filters
     *
     * @throws AttributeGroupNotFoundException
     */
    public function getOneBy(array $filters): AttributeGroupInterface;

    public function save(AttributeGroupInterface $group): void;

    public function remove(AttributeGroupInterface $group): void;

    /**
     * @param AttributeGroupRepositoryFilters $filters
     * @param AttributeGroupRepositorySortBy $sortBy
     * @param AttributeGroupRepositorySelects $selects
     *
     * @return iterable<AttributeGroupInterface>
     */
    public function findBy(array $filters = [], array $sortBy = [], array $selects = []): iterable;
}
