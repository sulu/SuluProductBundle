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
use Symfony\Component\Uid\Uuid;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type AttributeGroupRepositoryFilters from AttributeGroupRepositoryInterface
 * @phpstan-import-type AttributeGroupRepositorySortBy from AttributeGroupRepositoryInterface
 * @phpstan-import-type AttributeGroupRepositorySelects from AttributeGroupRepositoryInterface
 */
final class AttributeGroupRepository implements AttributeGroupRepositoryInterface
{
    private const SELECTS = [
        // GROUPS
        self::GROUP_SELECT_PRODUCT_FAMILY_FORM => [
            self::SELECT_GROUP_ATTRIBUTES => true,
            self::SELECT_GROUP_ATTRIBUTE_TRANSLATIONS => true,
            self::SELECT_GROUP_TRANSLATIONS => true,
        ],
    ];

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
        $selects = $this->resolveSelectGroups($selects);

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
                if ('id' === $field) {
                    $queryBuilder->addOrderBy('attributeGroup.id', $order);
                } elseif ('externalIdentifier' === $field) {
                    $queryBuilder->addOrderBy('attributeGroup.externalIdentifier', $order);
                }
            }
        }

        // selects
        if ($selects[self::SELECT_GROUP_ATTRIBUTES] ?? false) {
            $queryBuilder
                ->addSelect('groupAttribute', 'attribute')
                ->leftJoin('attributeGroup.groupAttributes', 'groupAttribute')
                ->leftJoin('groupAttribute.attribute', 'attribute');
        }

        if ($selects[self::SELECT_GROUP_ATTRIBUTE_TRANSLATIONS] ?? false) {
            Assert::notFalse($selects[self::SELECT_GROUP_ATTRIBUTES] ?? false);

            // Translations stay unfiltered, callers fall back to the default locale.
            $queryBuilder
                ->addSelect('attributeTranslation')
                ->leftJoin('attribute.translations', 'attributeTranslation');
        }

        return $queryBuilder;
    }

    public function findBy(array $filters = [], array $sortBy = [], array $selects = []): iterable
    {
        /** @var iterable<AttributeGroupInterface> $groups */
        $groups = $this->createQueryBuilder($filters, $sortBy, $selects)
            ->getQuery()
            ->getResult();

        $selects = $this->resolveSelectGroups($selects);

        if ($selects[self::SELECT_GROUP_TRANSLATIONS] ?? false) {
            // Own query, joined they would multiply the attribute rows above. Hydrating them fills
            // the groups' collections, the result itself is not needed.
            $this->createQueryBuilder($filters)
                ->addSelect('groupTranslation')
                ->leftJoin('attributeGroup.translations', 'groupTranslation')
                ->getQuery()
                ->getResult();
        }

        return $groups;
    }

    /**
     * @param AttributeGroupRepositorySelects $selects
     *
     * @return AttributeGroupRepositorySelects
     */
    private function resolveSelectGroups(array $selects): array
    {
        foreach ($selects as $selectGroup => $value) {
            if (!$value || !isset(self::SELECTS[$selectGroup])) {
                continue;
            }

            foreach (self::SELECTS[$selectGroup] as $select => $selectValue) {
                $selects[$select] = $selectValue;
            }
        }

        return $selects;
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
