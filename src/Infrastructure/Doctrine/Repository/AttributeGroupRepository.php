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
use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type AttributeGroupRepositoryFilters from AttributeGroupRepositoryInterface
 * @phpstan-import-type AttributeGroupRepositorySortBy from AttributeGroupRepositoryInterface
 * @phpstan-import-type AttributeGroupRepositorySelects from AttributeGroupRepositoryInterface
 */
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

    public function createNew(?string $uuid = null): AttributeGroupInterface
    {
        return new AttributeGroup($uuid);
    }

    public function findOneBy(array $filters): ?AttributeGroupInterface
    {
        $queryBuilder = $this->createQueryBuilder($filters);

        try {
            /** @var AttributeGroupInterface $group */
            $group = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException) {
            return null;
        }

        return $group;
    }

    public function getOneBy(array $filters): AttributeGroupInterface
    {
        $queryBuilder = $this->createQueryBuilder($filters);

        try {
            /** @var AttributeGroupInterface $group */
            $group = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            throw new AttributeGroupNotFoundException($filters, $e);
        }

        return $group;
    }

    /**
     * @param AttributeGroupRepositoryFilters $filters
     * @param AttributeGroupRepositorySortBy $sortBy
     * @param AttributeGroupRepositorySelects $selects
     */
    public function createQueryBuilder(array $filters, array $sortBy = [], array $selects = []): QueryBuilder
    {
        $queryBuilder = $this->entityRepository->createQueryBuilder('attributeGroup');

        $uuid = $filters['uuid'] ?? null;
        if (null !== $uuid) {
            Assert::string($uuid); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('attributeGroup.uuid = :uuid')
                ->setParameter('uuid', $uuid);
        }

        $externalIdentifier = $filters['externalIdentifier'] ?? null;
        if (null !== $externalIdentifier) {
            Assert::string($externalIdentifier); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('attributeGroup.externalIdentifier = :externalIdentifier')
                ->setParameter('externalIdentifier', $externalIdentifier);
        }

        if ([] !== $sortBy) {
            foreach ($sortBy as $field => $order) {
                if ('uuid' === $field) {
                    $queryBuilder->addOrderBy('attributeGroup.uuid', $order);
                } elseif ('externalIdentifier' === $field) {
                    $queryBuilder->addOrderBy('attributeGroup.externalIdentifier', $order);
                }
            }
        }

        return $queryBuilder;
    }

    public function findBy(array $filters = [], array $sortBy = [], array $selects = []): iterable
    {
        /** @var iterable<AttributeGroupInterface> $groups */
        $groups = $this->createQueryBuilder($filters, $sortBy, $selects)
            ->getQuery()
            ->getResult();

        if ($selects[self::SELECT_GROUP_TRANSLATIONS] ?? false) {
            // Own query, so translations are hydrated without multiplying the group rows.
            $this->createQueryBuilder($filters)
                ->addSelect('groupTranslation')
                ->leftJoin('attributeGroup.translations', 'groupTranslation')
                ->getQuery()
                ->getResult();
        }

        return $groups;
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
