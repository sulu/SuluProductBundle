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

use Sulu\Product\Domain\Exception\AttributeNotFoundException;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeInterface;

/**
 * @phpstan-type AttributeRepositoryFilters array{
 *     uuid?: string,
 *     key?: string,
 * }
 */
interface AttributeRepositoryInterface
{
    public function create(AttributeGroupInterface $group): AttributeInterface;

    /**
     * @param AttributeRepositoryFilters $filters
     */
    public function findOneBy(array $filters): ?AttributeInterface;

    /**
     * @param AttributeRepositoryFilters $filters
     *
     * @throws AttributeNotFoundException
     */
    public function getOneBy(array $filters): AttributeInterface;

    /** @param array<string, mixed> $criteria */
    public function countBy(array $criteria): int;

    /** @internal TODO: move to a Doctrine listener */
    public function findNextPositionInGroup(AttributeGroupInterface $group): int;

    /**
     * @return AttributeInterface[]
     *
     * @internal TODO: move to a Doctrine listener
     */
    public function findByGroupWithPositionAtLeast(AttributeGroupInterface $group, int $position, ?AttributeInterface $exclude = null): array;

    /**
     * @return AttributeInterface[]
     *
     * @internal TODO: move to a Doctrine listener
     */
    public function findByGroupWithPositionBetween(AttributeGroupInterface $group, int $min, int $max, ?AttributeInterface $exclude = null): array;

    public function save(AttributeInterface $attribute): void;

    public function remove(AttributeInterface $attribute): void;
}
