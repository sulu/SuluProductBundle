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
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type AttributeRepositoryFilters from AttributeRepositoryInterface
 * @phpstan-import-type AttributeRepositorySelects from AttributeRepositoryInterface
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

    public function createNew(AttributeGroupInterface $group, ?string $uuid = null): AttributeInterface
    {
        return new Attribute($group, $uuid);
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

    public function findBy(array $filters = [], array $selects = []): array
    {
        $queryBuilder = $this->createQueryBuilder($filters, $selects);

        /** @var list<AttributeInterface> $result */
        $result = $queryBuilder->getQuery()->getResult();

        $this->preloadCollections($result, $selects);

        return $result;
    }

    /**
     * @param AttributeRepositoryFilters $filters
     * @param AttributeRepositorySelects $selects
     */
    public function createQueryBuilder(array $filters, array $selects = []): QueryBuilder
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

        $externalIdentifier = $filters['externalIdentifier'] ?? null;
        if (null !== $externalIdentifier) {
            Assert::string($externalIdentifier); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('attribute.externalIdentifier = :externalIdentifier')
                ->setParameter('externalIdentifier', $externalIdentifier);
        }

        // selects
        if ($selects[self::SELECT_ATTRIBUTE_TRANSLATIONS] ?? false) {
            $queryBuilder
                ->addSelect('attributeTranslation')
                ->leftJoin('attribute.translations', 'attributeTranslation');
        }

        if ($selects[self::SELECT_ATTRIBUTE_GROUP] ?? false) {
            $queryBuilder
                ->addSelect('attributeGroup')
                ->innerJoin('attribute.group', 'attributeGroup');
        }

        return $queryBuilder;
    }

    /**
     * Own queries, joined they would multiply the rows of the main query. Hydrating them fills the
     * collections of the loaded entities, the results themselves are not needed.
     *
     * @param list<AttributeInterface> $attributes
     * @param AttributeRepositorySelects $selects
     */
    private function preloadCollections(array $attributes, array $selects): void
    {
        if ([] === $attributes) {
            return;
        }

        if ($selects[self::SELECT_ATTRIBUTE_GROUP] ?? false) {
            $this->entityManager->createQueryBuilder()
                ->select('attributeGroup', 'attributeGroupTranslation')
                ->from(AttributeGroup::class, 'attributeGroup')
                ->leftJoin('attributeGroup.translations', 'attributeGroupTranslation')
                ->where('attributeGroup IN (:groups)')
                ->setParameter('groups', \array_values(\array_unique(
                    \array_map(static fn (AttributeInterface $attribute): string => $attribute->getGroup()->getUuid(), $attributes),
                )))
                ->getQuery()
                ->getResult();
        }

        if ($selects[self::SELECT_ATTRIBUTE_OPTIONS] ?? false) {
            $this->entityManager->createQueryBuilder()
                ->select('attribute', 'option', 'optionTranslation')
                ->from(Attribute::class, 'attribute')
                ->leftJoin('attribute.options', 'option')
                ->leftJoin('option.translations', 'optionTranslation')
                ->where('attribute IN (:attributes)')
                ->setParameter('attributes', $attributes)
                ->getQuery()
                ->getResult();
        }
    }

    /** @param array<string, mixed> $criteria */
    public function countBy(array $criteria): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a)')
            ->from(Attribute::class, 'a');

        foreach ($criteria as $field => $value) {
            $qb->andWhere("a.{$field} = :{$field}")->setParameter($field, $value);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findNextPositionInGroup(AttributeGroupInterface $group): int
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('MAX(a.position)')
            ->from(Attribute::class, 'a')
            ->where('a.group = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $result ? (int) $result + 1 : 0;
    }

    public function findByGroupWithPositionAtLeast(AttributeGroupInterface $group, int $position, ?AttributeInterface $exclude = null): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Attribute::class, 'a')
            ->where('a.group = :group')
            ->andWhere('a.position >= :position')
            ->setParameter('group', $group)
            ->setParameter('position', $position);

        if (null !== $exclude) {
            $qb->andWhere('a != :exclude')->setParameter('exclude', $exclude);
        }

        /** @var AttributeInterface[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function findByGroupWithPositionBetween(AttributeGroupInterface $group, int $min, int $max, ?AttributeInterface $exclude = null): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Attribute::class, 'a')
            ->where('a.group = :group')
            ->andWhere('a.position >= :min')
            ->andWhere('a.position <= :max')
            ->setParameter('group', $group)
            ->setParameter('min', $min)
            ->setParameter('max', $max);

        if (null !== $exclude) {
            $qb->andWhere('a != :exclude')->setParameter('exclude', $exclude);
        }

        /** @var AttributeInterface[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
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
