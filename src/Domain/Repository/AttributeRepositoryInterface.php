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

interface AttributeRepositoryInterface
{
    public function create(): AttributeInterface;

    /** @param array<string, mixed> $criteria */
    public function findOneBy(array $criteria): ?AttributeInterface;

    public function save(AttributeInterface $attribute): void;

    public function remove(AttributeInterface $attribute): void;
}
