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
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class AttributeGroupRepository implements AttributeGroupRepositoryInterface
{
    /** @var EntityRepository<AttributeGroupInterface> */
    private EntityRepository $entityRepository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        /** @var EntityRepository<AttributeGroupInterface> $repo */
        $repo = $this->entityManager->getRepository(AttributeGroup::class);
        $this->entityRepository = $repo;
    }

    public function create(): AttributeGroupInterface
    {
        $group = new AttributeGroup();
        $group->setUuid(Uuid::v7()->toRfc4122());

        return $group;
    }

    /** @param array<string, mixed> $criteria */
    public function findOneBy(array $criteria): ?AttributeGroupInterface
    {
        /** @var AttributeGroupInterface|null $group */
        $group = $this->entityRepository->findOneBy($criteria);

        return $group;
    }

    public function save(AttributeGroupInterface $group): void
    {
        $this->entityManager->persist($group);
    }

    public function remove(AttributeGroupInterface $group): void
    {
        $this->entityManager->remove($group);
    }
}
