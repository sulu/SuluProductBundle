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
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Sulu\Product\Domain\Exception\AttributeNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Symfony\Component\Uid\Uuid;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type AttributeRepositoryFilters from AttributeRepositoryInterface
 */
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

    public function findOneBy(array $filters): ?AttributeInterface
    {
        $queryBuilder = $this->createQueryBuilder($filters);

        try {
            /** @var AttributeInterface $attribute */
            $attribute = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException) {
            return null;
        }

        return $attribute;
    }

    public function getOneBy(array $filters): AttributeInterface
    {
        $queryBuilder = $this->createQueryBuilder($filters);

        try {
            /** @var AttributeInterface $attribute */
            $attribute = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            throw new AttributeNotFoundException($filters, $e);
        }

        return $attribute;
    }

    /**
     * @param AttributeRepositoryFilters $filters
     */
    public function createQueryBuilder(array $filters): QueryBuilder
    {
        $queryBuilder = $this->entityRepository->createQueryBuilder('attribute');

        $uuid = $filters['uuid'] ?? null;
        if (null !== $uuid) {
            Assert::string($uuid); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('attribute.uuid = :uuid')
                ->setParameter('uuid', $uuid);
        }

        $key = $filters['key'] ?? null;
        if (null !== $key) {
            Assert::string($key); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('attribute.key = :key')
                ->setParameter('key', $key);
        }

        return $queryBuilder;
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
