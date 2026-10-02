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
use Doctrine\ORM\QueryBuilder;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Repository\ProductAttributeValueRepositoryInterface;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type ProductAttributeValueRepositoryFilters from ProductAttributeValueRepositoryInterface
 */
final class ProductAttributeValueRepository implements ProductAttributeValueRepositoryInterface
{
    /** @var EntityRepository<ProductAttributeValueInterface> */
    private EntityRepository $entityRepository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        /** @var EntityRepository<ProductAttributeValueInterface> $repo */
        $repo = $this->entityManager->getRepository(ProductAttributeValue::class);
        $this->entityRepository = $repo;
    }

    public function findBy(array $filters = []): array
    {
        $queryBuilder = $this->createQueryBuilder($filters);

        /** @var list<ProductAttributeValueInterface> $result */
        $result = $queryBuilder->getQuery()->getResult();

        return $result;
    }

    public function countValues(array $filters = [], int $limit = 100): array
    {
        $queryBuilder = $this->createQueryBuilder($filters)
            ->select('MIN(attributeValue.id) AS representativeId', 'COUNT(attributeValue.id) AS valueCount')
            ->leftJoin('attributeValue.attributeOption', 'attributeOption')
            ->andWhere("(attributeValue.text IS NOT NULL AND attributeValue.text <> '') OR attributeValue.number IS NOT NULL OR attributeOption.uuid IS NOT NULL")
            ->groupBy('attributeValue.text')
            ->addGroupBy('attributeValue.number')
            ->addGroupBy('attributeOption.uuid')
            ->orderBy('valueCount', 'DESC')
            ->addOrderBy('representativeId', 'ASC')
            ->setMaxResults($limit);

        /** @var list<array{representativeId: string, valueCount: string}> $rows */
        $rows = $queryBuilder->getQuery()->getScalarResult();

        if ([] === $rows) {
            return [];
        }

        /** @var list<ProductAttributeValueInterface> $representatives */
        $representatives = $this->entityRepository->findBy(['id' => \array_column($rows, 'representativeId')]);

        $byId = [];
        foreach ($representatives as $representative) {
            $byId[$representative->getId()] = $representative;
        }

        $groups = [];
        foreach ($rows as $row) {
            $value = $byId[(int) $row['representativeId']] ?? null;

            if (null !== $value) {
                $groups[] = ['value' => $value, 'count' => (int) $row['valueCount']];
            }
        }

        return $groups;
    }

    /**
     * @param ProductAttributeValueRepositoryFilters $filters
     */
    public function createQueryBuilder(array $filters): QueryBuilder
    {
        $queryBuilder = $this->entityRepository->createQueryBuilder('attributeValue')
            ->innerJoin('attributeValue.productDimensionContent', 'dimensionContent')
            ->andWhere('dimensionContent.version = :version')
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION);

        $attribute = $filters['attribute'] ?? null;
        if (null !== $attribute) {
            $queryBuilder->andWhere('attributeValue.attribute = :attribute')
                ->setParameter('attribute', $attribute);
        }

        $stage = $filters['stage'] ?? null;
        if (null !== $stage) {
            Assert::string($stage); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('dimensionContent.stage = :stage')
                ->setParameter('stage', $stage);
        }

        $locale = $filters['locale'] ?? null;
        if (null !== $locale) {
            Assert::string($locale); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('dimensionContent.locale = :locale OR dimensionContent.locale IS NULL')
                ->setParameter('locale', $locale);
        }

        return $queryBuilder;
    }
}
