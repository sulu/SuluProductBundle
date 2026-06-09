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

use Sulu\Product\Domain\Model\AttributeSetInterface;

interface AttributeSetRepositoryInterface
{
    public function create(): AttributeSetInterface;

    /** @param array<string, mixed> $criteria */
    public function findOneBy(array $criteria): ?AttributeSetInterface;

    public function save(AttributeSetInterface $attributeSet): void;

    public function remove(AttributeSetInterface $attributeSet): void;
}
