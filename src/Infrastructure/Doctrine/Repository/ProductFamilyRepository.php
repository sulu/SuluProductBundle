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
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Symfony\Component\Uid\Uuid;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type ProductFamilyRepositoryFilters from ProductFamilyRepositoryInterface
 */
final class ProductFamilyRepository implements ProductFamilyRepositoryInterface
{
    /** @var EntityRepository<ProductFamilyInterface> */
    private EntityRepository $entityRepository;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        /** @var EntityRepository<ProductFamilyInterface> $repo */
        $repo = $this->entityManager->getRepository(ProductFamily::class);
        $this->entityRepository = $repo;
    }

    public function create(): ProductFamilyInterface
    {
        $family = new ProductFamily();
        $family->setUuid(Uuid::v7()->toRfc4122());

        return $family;
    }

    public function findOneBy(array $filters): ?ProductFamilyInterface
    {
        $queryBuilder = $this->createQueryBuilder($filters);

        try {
            /** @var ProductFamilyInterface $family */
            $family = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException) {
            return null;
        }

        return $family;
    }

    public function getOneBy(array $filters): ProductFamilyInterface
    {
        $queryBuilder = $this->createQueryBuilder($filters);

        try {
            /** @var ProductFamilyInterface $family */
            $family = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            throw new ProductFamilyNotFoundException($filters, $e);
        }

        return $family;
    }

    public function findBy(array $filters = []): iterable
    {
        /** @var iterable<ProductFamilyInterface> $result */
        $result = $this->createQueryBuilder($filters)->getQuery()->getResult();

        return $result;
    }

    public function countBy(array $filters = []): int
    {
        // The countBy method will ignore any page and limit parameters
        // for better developer experience we will strip them away here
        // instead of that the developer need to take that into account
        // in there call of the countBy method.
        unset($filters['page']); // @phpstan-ignore-line
        unset($filters['limit']); // @phpstan-ignore-line

        $queryBuilder = $this->createQueryBuilder($filters);

        $queryBuilder->select('COUNT(DISTINCT productFamily.id)');

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * @param ProductFamilyRepositoryFilters $filters
     */
    public function createQueryBuilder(array $filters): QueryBuilder
    {
        $queryBuilder = $this->entityRepository->createQueryBuilder('productFamily');

        $uuid = $filters['uuid'] ?? null;
        if (null !== $uuid) {
            Assert::string($uuid); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('productFamily.uuid = :uuid')
                ->setParameter('uuid', $uuid);
        }

        $uuids = $filters['uuids'] ?? null;
        if (null !== $uuids) {
            Assert::isArray($uuids); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('productFamily.uuid IN(:uuids)')
                ->setParameter('uuids', $uuids);
        }

        $externalIdentifier = $filters['externalIdentifier'] ?? null;
        if (null !== $externalIdentifier) {
            Assert::string($externalIdentifier); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('productFamily.externalIdentifier = :externalIdentifier')
                ->setParameter('externalIdentifier', $externalIdentifier);
        }

        $productUuid = $filters['productUuid'] ?? null;
        if (null !== $productUuid) {
            Assert::string($productUuid); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder
                ->innerJoin(
                    ProductDimensionContentInterface::class,
                    'productDimensionContent',
                    Join::WITH,
                    'productDimensionContent.productFamily = productFamily',
                )
                ->andWhere('productDimensionContent.locale IS NULL')
                ->andWhere('productDimensionContent.stage = :stage')
                ->andWhere('productDimensionContent.version = :version')
                ->andWhere('IDENTITY(productDimensionContent.product) = :productUuid')
                ->setParameter('stage', DimensionContentInterface::STAGE_DRAFT)
                ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
                ->setParameter('productUuid', $productUuid);
        }

        $limit = $filters['limit'] ?? null;
        if (null !== $limit) {
            Assert::integer($limit); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->setMaxResults($limit);
        }

        $page = $filters['page'] ?? null;
        if (null !== $page) {
            Assert::integer($page); // @phpstan-ignore staticMethod.alreadyNarrowedType
            Assert::notNull($limit);
            $offset = (int) ($limit * ($page - 1));
            $queryBuilder->setFirstResult($offset);
        }

        return $queryBuilder;
    }

    public function save(ProductFamilyInterface $family): void
    {
        $this->entityManager->persist($family);
    }

    public function remove(ProductFamilyInterface $family): void
    {
        $this->entityManager->remove($family);
    }
}
