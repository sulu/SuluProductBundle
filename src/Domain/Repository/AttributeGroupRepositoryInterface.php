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
 */
interface AttributeGroupRepositoryInterface
{
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
     * @return list<AttributeGroupInterface>
     */
    public function findAll(): array;
}
