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
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
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
                    'pdc',
                    Join::WITH,
                    'pdc.productFamily = productFamily',
                )
                ->andWhere('pdc.locale IS NULL')
                ->andWhere('IDENTITY(pdc.product) = :productUuid')
                ->setParameter('productUuid', $productUuid);
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
