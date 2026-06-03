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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\SmartContent\SmartContentQueryEnhancer;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductSmartContentProvider;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;

#[CoversClass(ProductSmartContentProvider::class)]
class ProductSmartContentProviderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    /** @var ObjectProphecy<EntityRepository<ProductInterface>> */
    private ObjectProphecy $productRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        /** @var ObjectProphecy<EntityRepository<ProductInterface>> $productRepository */
        $productRepository = $this->prophesize(EntityRepository::class);
        $this->productRepository = $productRepository;

        /** @var ObjectProphecy<EntityRepository<ProductDimensionContentInterface>> $dimensionContentRepository */
        $dimensionContentRepository = $this->prophesize(EntityRepository::class);
        $dimensionContentRepository->getClassName()->willReturn(ProductDimensionContent::class);

        $this->entityManager->getRepository(ProductInterface::class)
            ->willReturn($this->productRepository->reveal());
        $this->entityManager->getRepository(ProductDimensionContentInterface::class)
            ->willReturn($dimensionContentRepository->reveal());
    }

    private function createProvider(): ProductSmartContentProvider
    {
        return new ProductSmartContentProvider(
            new DimensionContentQueryEnhancer(),
            new SmartContentQueryEnhancer(),
            $this->entityManager->reveal(),
        );
    }

    /**
     * @return ObjectProphecy<QueryBuilder>
     */
    private function createQueryBuilderProphecy(): ObjectProphecy
    {
        /** @var ObjectProphecy<QueryBuilder> $queryBuilder */
        $queryBuilder = $this->prophesize(QueryBuilder::class);

        // All fluent methods return $this
        $queryBuilder->leftJoin(Argument::cetera())->willReturn($queryBuilder->reveal());
        $queryBuilder->innerJoin(Argument::cetera())->willReturn($queryBuilder->reveal());
        $queryBuilder->andWhere(Argument::cetera())->willReturn($queryBuilder->reveal());
        $queryBuilder->setParameter(Argument::cetera())->willReturn($queryBuilder->reveal());
        $queryBuilder->select(Argument::cetera())->willReturn($queryBuilder->reveal());
        $queryBuilder->addSelect(Argument::cetera())->willReturn($queryBuilder->reveal());
        $queryBuilder->addOrderBy(Argument::cetera())->willReturn($queryBuilder->reveal());
        $queryBuilder->setMaxResults(Argument::cetera())->willReturn($queryBuilder->reveal());
        $queryBuilder->setFirstResult(Argument::cetera())->willReturn($queryBuilder->reveal());
        $queryBuilder->getDQLPart(Argument::any())->willReturn([]);
        $queryBuilder->expr()->willReturn(new Expr());

        return $queryBuilder;
    }

    /**
     * @return array{
     *     locale: string,
     *     dataSource: null,
     *     categories: int[],
     *     categoryOperator: 'AND',
     *     websiteCategories: string[],
     *     websiteCategoryOperator: 'AND',
     *     tags: int[],
     *     tagOperator: 'AND',
     *     websiteTags: string[],
     *     websiteTagOperator: 'AND',
     *     includeSubFolders: false,
     *     excludeDuplicates: false,
     *     limit: null,
     *     offset: int,
     * }
     */
    private function minimalFilters(): array
    {
        return [
            'locale' => 'en',
            'dataSource' => null,
            'categories' => [],
            'categoryOperator' => 'AND',
            'websiteCategories' => [],
            'websiteCategoryOperator' => 'AND',
            'tags' => [],
            'tagOperator' => 'AND',
            'websiteTags' => [],
            'websiteTagOperator' => 'AND',
            'includeSubFolders' => false,
            'excludeDuplicates' => false,
            'limit' => null,
            'offset' => 0,
        ];
    }

    public function testGetType(): void
    {
        $this->assertSame(ProductInterface::RESOURCE_KEY, $this->createProvider()->getType());
    }

    public function testGetResourceLoaderKey(): void
    {
        $this->assertSame(
            ProductResourceLoader::RESOURCE_LOADER_KEY,
            $this->createProvider()->getResourceLoaderKey(),
        );
    }

    public function testGetConfigurationReturnsBuiltConfiguration(): void
    {
        $configuration = $this->createProvider()->getConfiguration();

        $this->assertTrue($configuration->hasTags());
        $this->assertTrue($configuration->hasCategories());
        $this->assertTrue($configuration->hasLimit());
        $this->assertTrue($configuration->hasPagination());
        $this->assertTrue($configuration->hasPresentAs());
        $this->assertTrue($configuration->hasSorting());
    }

    public function testCountByReturnsCount(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getSingleScalarResult()->willReturn(0);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $result = $this->createProvider()->countBy($this->minimalFilters());

        $this->assertSame(0, $result);
    }

    public function testFindFlatByReturnsResults(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getArrayResult()->willReturn([]);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $result = $this->createProvider()->findFlatBy($this->minimalFilters(), []);

        $this->assertSame([], $result);
    }

    public function testMapFiltersWithCategories(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getSingleScalarResult()->willReturn(3);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $filters = $this->minimalFilters();
        $filters['categories'] = [1, 2, 3];

        $result = $this->createProvider()->countBy($filters);

        $this->assertSame(3, $result);
    }

    public function testMapFiltersWithTags(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getSingleScalarResult()->willReturn(2);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $filters = $this->minimalFilters();
        $filters['tags'] = [10, 20];

        $result = $this->createProvider()->countBy($filters);

        $this->assertSame(2, $result);
    }

    public function testMapFiltersWithTemplateKeysFromParams(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getArrayResult()->willReturn([]);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $filters = $this->minimalFilters();
        // templateKeys in params — no templateKeys in filters means the param list is used directly
        $result = $this->createProvider()->findFlatBy($filters, [], ['templateKeys' => 'default, overview']);

        $this->assertSame([], $result);
    }

    public function testMapFiltersTemplateKeysIntersectionWithParams(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getArrayResult()->willReturn([]);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $filters = $this->minimalFilters();
        $filters['templateKeys'] = ['default', 'overview'];
        // param restricts to 'default' only — intersection should leave just that
        $result = $this->createProvider()->findFlatBy($filters, [], ['templateKeys' => 'default']);

        $this->assertSame([], $result);
    }

    public function testMapSortBysMapsPublished(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getArrayResult()->willReturn([]);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        // 'published' in sortBys must be mapped to 'workflowPublished' before being passed to DimensionContentQueryEnhancer
        // which would call addOrderBy('filterDimensionContent.workflowPublished', 'asc')
        $queryBuilder->addOrderBy('filterDimensionContent.workflowPublished', 'asc')->willReturn($queryBuilder->reveal());

        $result = $this->createProvider()->findFlatBy($this->minimalFilters(), ['published' => 'asc']);

        $this->assertSame([], $result);
        // Verify the mapped sort column was used
        $queryBuilder->addOrderBy('filterDimensionContent.workflowPublished', 'asc')->shouldHaveBeenCalled();
    }

    public function testAddInternalFiltersWithWebsiteCategories(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getSingleScalarResult()->willReturn(1);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $filters = $this->minimalFilters();
        $filters['websiteCategories'] = ['cat-1', 'cat-2'];
        $filters['websiteCategoryOperator'] = 'AND';

        $result = $this->createProvider()->countBy($filters);

        $this->assertSame(1, $result);
        // SmartContentQueryEnhancer::addJoinFilter is called with AND operator → one leftJoin per category
        $queryBuilder->leftJoin(
            'filterDimensionContent.excerptCategories',
            Argument::containingString('websiteFilterCategoryId'),
        )->shouldHaveBeenCalled();
    }

    public function testAddInternalFiltersWithWebsiteTags(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getSingleScalarResult()->willReturn(0);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $filters = $this->minimalFilters();
        $filters['websiteTags'] = ['tag-a', 'tag-b'];
        $filters['websiteTagOperator'] = 'OR';

        $result = $this->createProvider()->countBy($filters);

        $this->assertSame(0, $result);
        // SmartContentQueryEnhancer::addJoinFilter with OR operator → one leftJoin with the join path
        $queryBuilder->leftJoin(
            'filterDimensionContent.excerptTags',
            'websiteFilterTagName',
        )->shouldHaveBeenCalled();
    }

    public function testAddInternalFiltersWithWebspaceKey(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getSingleScalarResult()->willReturn(5);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $filters = $this->minimalFilters();
        $filters['webspaceKey'] = 'sulu_io';

        $result = $this->createProvider()->countBy($filters);

        $this->assertSame(5, $result);
        $queryBuilder->leftJoin('filterDimensionContent.additionalWebspaces', 'additionalWebspace')->shouldHaveBeenCalled();
        $queryBuilder->setParameter('webspaceKey', 'sulu_io')->shouldHaveBeenCalled();
    }
}
