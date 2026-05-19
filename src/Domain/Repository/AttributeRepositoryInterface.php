<?php

declare(strict_types=1);

namespace Sulu\Product\Domain\Repository;

use Sulu\Product\Domain\Model\AttributeInterface;

interface AttributeRepositoryInterface
{
    public function create(): AttributeInterface;

    public function findOneBy(array $criteria): ?AttributeInterface;

    public function save(AttributeInterface $attribute): void;

    public function remove(AttributeInterface $attribute): void;
}
