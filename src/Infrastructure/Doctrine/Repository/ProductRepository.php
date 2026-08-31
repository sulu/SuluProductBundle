<?php

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
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Webmozart\Assert\Assert;

final class ProductRepository implements ProductRepositoryInterface
{
    /**
     * TODO it should be possible to extend fields and groups inside the SELECTS.
     */
    private const SELECTS = [
        // GROUPS
        self::GROUP_SELECT_PRODUCT_ADMIN => [
            self::SELECT_PRODUCT_CONTENT => [
                DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true,
            ],
        ],
        self::GROUP_SELECT_PRODUCT_WEBSITE => [
            self::SELECT_PRODUCT_CONTENT => [
                DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_WEBSITE => true,
            ],
        ],
    ];

    /**
     * The query alias each sortable field lives on. A `filterDimensionContent` field sorts only
     * where addFilters() joined that table.
     *
     * @var array<string, string>
     */
    private const SORT_ALIASES = [
        'uuid' => 'product',
        'position' => 'product',
        'created' => 'product',
        'changed' => 'product',
        'title' => 'filterDimensionContent',
        'authored' => 'filterDimensionContent',
        'workflowPublished' => 'filterDimensionContent',
    ];

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var EntityRepository<ProductInterface>
     */
    private $entityRepository;

    /**
     * @var EntityRepository<ProductDimensionContentInterface>
     */
    private $entityDimensionContentRepository;

    /**
     * @var DimensionContentQueryEnhancer
     */
    private $dimensionContentQueryEnhancer;

    /**
     * @var class-string<ProductInterface>
     */
    private $productClassName;

    /**
     * @var class-string<ProductDimensionContentInterface>
     */
    private $productDimensionContentClassName;

    public function __construct(
        EntityManagerInterface $entityManager,
        DimensionContentQueryEnhancer $dimensionContentQueryEnhancer,
    ) {
        $this->entityRepository = $entityManager->getRepository(ProductInterface::class);
        $this->entityDimensionContentRepository = $entityManager->getRepository(ProductDimensionContentInterface::class);
        $this->entityManager = $entityManager;
        $this->dimensionContentQueryEnhancer = $dimensionContentQueryEnhancer;
        $this->productClassName = $this->entityRepository->getClassName();
        $this->productDimensionContentClassName = $this->entityDimensionContentRepository->getClassName();
    }

    public function createNew(?string $uuid = null): ProductInterface
    {
        $className = $this->productClassName;

        return new $className($uuid);
    }

    public function getOneBy(array $filters, array $selects = []): ProductInterface
    {
        $queryBuilder = $this->createQueryBuilder($filters, [], $selects);

        try {
            /** @var ProductInterface $product */
            $product = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            throw new ProductNotFoundException($filters, 0, $e);
        }

        return $product;
    }

    public function findOneBy(array $filters, array $selects = []): ?ProductInterface
    {
        $queryBuilder = $this->createQueryBuilder($filters, [], $selects);

        try {
            /** @var ProductInterface $product */
            $product = $queryBuilder->getQuery()->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        }

        return $product;
    }

    public function existBy(array $filters): bool
    {
        $queryBuilder = $this->entityRepository->createQueryBuilder('product')
            ->select('COUNT(product.uuid)');

        $code = $filters['code'] ?? null;
        if (null !== $code) {
            $queryBuilder
                ->innerJoin('product.dimensionContents', 'pdcCode')
                ->andWhere('pdcCode.code = :code')
                ->andWhere('pdcCode.locale IS NULL')
                ->andWhere('pdcCode.stage = :stage')
                ->andWhere('pdcCode.version = :version')
                ->setParameter('code', $code)
                ->setParameter('stage', DimensionContentInterface::STAGE_DRAFT)
                ->setParameter('version', DimensionContentInterface::CURRENT_VERSION);
        }

        $productFamilyUuid = $filters['productFamilyUuid'] ?? null;
        if (null !== $productFamilyUuid) {
            $queryBuilder
                ->innerJoin('product.dimensionContents', 'pdcFamily')
                ->innerJoin('pdcFamily.productFamily', 'productFamily')
                ->andWhere('pdcFamily.locale IS NULL')
                ->andWhere('productFamily.uuid = :productFamilyUuid')
                ->setParameter('productFamilyUuid', $productFamilyUuid);
        }

        $excludeUuid = $filters['excludeUuid'] ?? null;
        if (null !== $excludeUuid) {
            $queryBuilder
                ->andWhere('product.uuid != :excludeUuid')
                ->setParameter('excludeUuid', $excludeUuid);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult() > 0;
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

        $queryBuilder->select('COUNT(DISTINCT product.uuid)');

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * @return \Generator<ProductInterface>
     */
    public function findBy(array $filters = [], array $sortBy = [], array $selects = []): \Generator
    {
        $queryBuilder = $this->createQueryBuilder($filters, $sortBy, $selects);

        /** @var iterable<ProductInterface> $products */
        $products = $queryBuilder->getQuery()->getResult();

        foreach ($products as $product) {
            yield $product;
        }
    }

    public function findIdentifiersBy(array $filters = [], array $sortBy = []): iterable
    {
        $queryBuilder = $this->createQueryBuilder($filters, $sortBy);

        $queryBuilder->select('DISTINCT product.uuid');

        // we need to select the fields which are used in the order by clause

        /** @var OrderBy[] $orderBys */
        $orderBys = $queryBuilder->getDQLPart('orderBy');
        foreach ($orderBys as $orderBy) {
            $queryBuilder->addSelect(\explode(' ', $orderBy->getParts()[0])[0]);
        }

        /** @var array<array{uuid: string}> $result */
        $result = $queryBuilder->getQuery()->getResult();

        return \array_column($result, 'uuid');
    }

    public function findSlugsBy(array $filters): array
    {
        if ([] === $filters['uuids']) {
            return [];
        }

        /** @var list<array{uuid: string, slug: string|null}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('product.uuid', 'route.slug')
            ->from($this->productDimensionContentClassName, 'dimensionContent')
            ->join('dimensionContent.product', 'product')
            ->leftJoin('dimensionContent.route', 'route')
            ->where('product.uuid IN (:uuids)')
            ->andWhere('dimensionContent.locale = :locale')
            ->andWhere('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.version = :version')
            ->setParameter('uuids', $filters['uuids'])
            ->setParameter('locale', $filters['locale'])
            ->setParameter('stage', $filters['stage'])
            ->setParameter('version', DimensionContentInterface::CURRENT_VERSION)
            ->getQuery()
            ->getArrayResult();

        return \array_column($rows, 'slug', 'uuid');
    }

    public function add(ProductInterface $product): void
    {
        $this->entityManager->persist($product);
    }

    public function remove(ProductInterface $product): void
    {
        $this->entityManager->remove($product);
    }

    public function removeDimensionContent(DimensionContentInterface $dimensionContent): void
    {
        $this->entityManager->remove($dimensionContent);
    }

    /**
     * @param array{
     *     uuid?: string,
     *     uuids?: string[],
     *     locale?: string|null,
     *     stage?: string|null,
     *     categoryIds?: int[],
     *     categoryKeys?: string[],
     *     categoryOperator?: 'AND'|'OR',
     *     tagIds?: int[],
     *     tagNames?: string[],
     *     tagOperator?: 'AND'|'OR',
     *     templateKeys?: string[],
     *     loadGhost?: bool,
     *     associationTargetUuid?: string,
     *     associationType?: string,
     *     parent?: string,
     *     types?: string[],
     *     excludeTypes?: string[],
     *     page?: int,
     *     limit?: int,
     * } $filters
     * @param array<string, 'asc'|'desc'> $sortBy fields from self::SORT_ALIASES; the key order is
     *                                            the ORDER BY order. Unknown fields are ignored,
     *                                            a filterDimensionContent field without a `locale`
     *                                            and `stage` filter throws
     * @param array{
     *     product_admin?: bool,
     *     product_website?: bool,
     *     with-product-content?: bool|array<string, mixed>,
     * }|array<string, mixed> $selects
     */
    public function createQueryBuilder(array $filters, array $sortBy = [], array $selects = []): QueryBuilder
    {
        foreach ($selects as $selectGroup => $value) {
            if (!$value) {
                continue;
            }

            if (isset(self::SELECTS[$selectGroup])) {
                $selects = \array_replace_recursive($selects, self::SELECTS[$selectGroup]);
            }
        }

        $queryBuilder = $this->entityRepository->createQueryBuilder('product');

        $uuid = $filters['uuid'] ?? null;
        if (null !== $uuid) {
            Assert::string($uuid); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('product.uuid = :uuid')
                ->setParameter('uuid', $uuid);
        }

        $uuids = $filters['uuids'] ?? null;
        if (null !== $uuids) {
            Assert::isArray($uuids); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('product.uuid IN(:uuids)')
                ->setParameter('uuids', $uuids);
        }

        $parent = $filters['parent'] ?? null;
        if (null !== $parent) {
            Assert::string($parent); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('product.parent = :parent')
                ->setParameter('parent', $parent);
        }

        $types = $filters['types'] ?? null;
        if (null !== $types) {
            Assert::isArray($types); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('product.type IN(:types)')
                ->setParameter('types', $types);
        }

        $excludeTypes = $filters['excludeTypes'] ?? null;
        if (null !== $excludeTypes) {
            Assert::isArray($excludeTypes); // @phpstan-ignore staticMethod.alreadyNarrowedType
            $queryBuilder->andWhere('product.type NOT IN(:excludeTypes)')
                ->setParameter('excludeTypes', $excludeTypes);
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

        $hasDimensionContentJoin = (\array_key_exists('locale', $filters)       // should also work with locale = null
                && \array_key_exists('stage', $filters))
            || ([] === $filters && [] !== $sortBy);      // if no filters are set, but sortBy is set, we need to set the sorting

        if ($hasDimensionContentJoin) {
            $this->dimensionContentQueryEnhancer->addFilters(
                $queryBuilder,
                'product',
                $this->productDimensionContentClassName,
                $filters,
                // No sorting here: the enhancer appends its own fields to the ORDER BY before this
                // method reaches its own, so a field it knows would outrank every field it does not,
                // whatever order the caller asked for. Applied below in one pass instead.
                [],
            );
        }

        $associationTargetUuid = $filters['associationTargetUuid'] ?? null;
        if (null !== $associationTargetUuid) {
            Assert::string($associationTargetUuid); // @phpstan-ignore staticMethod.alreadyNarrowedType
            // Associations hang on the unlocalized dimension content, locale and stage restrict the referrers.
            if (!\array_key_exists('locale', $filters) || !\array_key_exists('stage', $filters)) {
                throw new \InvalidArgumentException('Filtering by "associationTargetUuid" requires both "locale" and "stage" filters.');
            }

            $dimensionContentClassName = $this->productDimensionContentClassName;
            $effectiveAttributes = $dimensionContentClassName::getEffectiveDimensionAttributes($filters);

            // An EXISTS semi-join, deliberately not a join over the association collection: a referrer can
            // point at the same target through several associations, because the unique constraint is
            // scoped per type. A collection join would emit one root row per association, and LIMIT/OFFSET
            // apply to rows and not to distinct products — pagination would silently drop referrers.
            $associationQueryBuilder = $this->entityManager->createQueryBuilder()
                ->select('associationDimensionContent.id')
                ->from($dimensionContentClassName, 'associationDimensionContent')
                ->innerJoin('associationDimensionContent.associations', 'productAssociation')
                ->where('associationDimensionContent.product = product')
                ->andWhere('associationDimensionContent.locale IS NULL')
                ->andWhere('associationDimensionContent.stage = :associationStage')
                ->andWhere('associationDimensionContent.version = :associationVersion')
                ->andWhere('IDENTITY(productAssociation.target) = :associationTargetUuid');

            $associationType = $filters['associationType'] ?? null;
            if (null !== $associationType) {
                Assert::string($associationType); // @phpstan-ignore staticMethod.alreadyNarrowedType
                $associationQueryBuilder->andWhere('productAssociation.type = :associationType');
                $queryBuilder->setParameter('associationType', $associationType);
            }

            $queryBuilder
                ->andWhere($queryBuilder->expr()->exists($associationQueryBuilder->getDQL()))
                ->setParameter('associationStage', $effectiveAttributes['stage'])
                ->setParameter('associationVersion', $effectiveAttributes['version'])
                ->setParameter('associationTargetUuid', $associationTargetUuid);
        }

        // One pass over the caller's keys, so their order is the ORDER BY order.
        foreach ($sortBy as $field => $order) {
            $alias = self::SORT_ALIASES[$field] ?? null;

            if (null === $alias) {
                continue;
            }

            // A field we know but cannot reach is a caller mistake, not a field to skip: silently
            // dropping it returns arbitrarily ordered rows with no signal.
            if ('filterDimensionContent' === $alias && !$hasDimensionContentJoin) {
                throw new \InvalidArgumentException(\sprintf('Sorting by "%s" requires both "locale" and "stage" filters.', $field));
            }

            $queryBuilder->addOrderBy($alias . '.' . $field, $order);
        }

        // selects
        if ($selects[self::SELECT_PRODUCT_CONTENT] ?? null) {
            /** @var array{dimensionAttributes?: array<string, mixed>, selects?: array<string, bool>} $contentConfig */
            $contentConfig = $selects[self::SELECT_PRODUCT_CONTENT];
            $queryBuilder->leftJoin(
                'product.dimensionContents',
                'dimensionContent',
            );

            if (isset($contentConfig['dimensionAttributes'])) {
                $contentSelects = $contentConfig['selects'] ?? [];
                $dimensionAttributes = $contentConfig['dimensionAttributes'];
            } else {
                /** @var array<string, bool> $contentSelects */
                $contentSelects = $contentConfig;
                $dimensionAttributes = $filters;
            }

            $this->dimensionContentQueryEnhancer->addSelects(
                $queryBuilder,
                $this->productDimensionContentClassName,
                $dimensionAttributes,
                $contentSelects,
            );
        }

        return $queryBuilder;
    }
}
