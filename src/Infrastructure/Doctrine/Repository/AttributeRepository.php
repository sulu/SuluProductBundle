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
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class AttributeRepository implements AttributeRepositoryInterface
{
    /** @var EntityRepository<AttributeInterface> */
    private EntityRepository $entityRepository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        /** @var EntityRepository<AttributeInterface> $repo */
        $repo = $this->entityManager->getRepository(Attribute::class);
        $this->entityRepository = $repo;
    }

    public function create(): AttributeInterface
    {
        $attribute = new Attribute();
        $attribute->setUuid(Uuid::v7()->toRfc4122());

        return $attribute;
    }

    /** @param array<string, mixed> $criteria */
    public function findOneBy(array $criteria): ?AttributeInterface
    {
        /** @var AttributeInterface|null $attribute */
        $attribute = $this->entityRepository->findOneBy($criteria);

        return $attribute;
    }

    public function save(AttributeInterface $attribute): void
    {
        $this->entityManager->persist($attribute);
    }

    public function remove(AttributeInterface $attribute): void
    {
        $this->entityManager->remove($attribute);
    }
}
