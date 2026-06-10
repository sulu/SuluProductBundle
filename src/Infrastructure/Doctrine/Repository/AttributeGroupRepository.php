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
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
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
        $attributeGroup = new AttributeGroup();
        $attributeGroup->setUuid(Uuid::v7()->toRfc4122());

        return $attributeGroup;
    }

    /** @param array<string, mixed> $criteria */
    public function findOneBy(array $criteria): ?AttributeGroupInterface
    {
        /** @var AttributeGroupInterface|null $attributeGroup */
        $attributeGroup = $this->entityRepository->findOneBy($criteria);

        return $attributeGroup;
    }

    public function countGroupAttributes(array $criteria): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(ga)')
            ->from(AttributeGroupAttribute::class, 'ga');

        foreach ($criteria as $field => $value) {
            $qb->andWhere("ga.{$field} = :{$field}")->setParameter($field, $value);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function save(AttributeGroupInterface $attributeGroup): void
    {
        $this->entityManager->persist($attributeGroup);
    }

    public function remove(AttributeGroupInterface $attributeGroup): void
    {
        $this->entityManager->remove($attributeGroup);
    }
}
