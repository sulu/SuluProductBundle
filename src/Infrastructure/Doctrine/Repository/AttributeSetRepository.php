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

namespace Sulu\Product\Infrastructure\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Sulu\Product\Domain\Model\AttributeSet;
use Sulu\Product\Domain\Model\AttributeSetInterface;
use Sulu\Product\Domain\Repository\AttributeSetRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class AttributeSetRepository implements AttributeSetRepositoryInterface
{
    /** @var EntityRepository<AttributeSetInterface> */
    private EntityRepository $entityRepository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        /** @var EntityRepository<AttributeSetInterface> $repo */
        $repo = $this->entityManager->getRepository(AttributeSet::class);
        $this->entityRepository = $repo;
    }

    public function create(): AttributeSetInterface
    {
        $attributeSet = new AttributeSet();
        $attributeSet->setUuid(Uuid::v7()->toRfc4122());

        return $attributeSet;
    }

    /** @param array<string, mixed> $criteria */
    public function findOneBy(array $criteria): ?AttributeSetInterface
    {
        /** @var AttributeSetInterface|null $attributeSet */
        $attributeSet = $this->entityRepository->findOneBy($criteria);

        return $attributeSet;
    }

    public function save(AttributeSetInterface $attributeSet): void
    {
        $this->entityManager->persist($attributeSet);
    }

    public function remove(AttributeSetInterface $attributeSet): void
    {
        $this->entityManager->remove($attributeSet);
    }
}
