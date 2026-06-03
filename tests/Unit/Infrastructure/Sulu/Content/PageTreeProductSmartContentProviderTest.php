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
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\PageTreeProductSmartContentProvider;
use Sulu\Route\Domain\Model\Route;

#[CoversClass(PageTreeProductSmartContentProvider::class)]
class PageTreeProductSmartContentProviderTest extends TestCase
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

    private function createProvider(): PageTreeProductSmartContentProvider
    {
        return new PageTreeProductSmartContentProvider(
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
        $queryBuilder->join(Argument::cetera())->willReturn($queryBuilder->reveal());
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
     *     dataSource: string|null,
     *     categories: int[],
     *     categoryOperator: 'AND',
     *     websiteCategories: string[],
     *     websiteCategoryOperator: 'AND',
     *     tags: int[],
     *     tagOperator: 'AND',
     *     websiteTags: string[],
     *     websiteTagOperator: 'AND',
     *     includeSubFolders: bool,
     *     excludeDuplicates: bool,
     *     limit: null,
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
        ];
    }

    public function testGetType(): void
    {
        $this->assertSame(PageTreeProductSmartContentProvider::PROVIDER_TYPE, $this->createProvider()->getType());
        $this->assertSame('products_page_tree', $this->createProvider()->getType());
    }

    public function testGetConfigurationEnablesDatasource(): void
    {
        $configuration = $this->createProvider()->getConfiguration();

        $this->assertTrue($configuration->hasDatasource());
    }

    public function testAddInternalFiltersSkipsRouteJoinWhenDataSourceIsNull(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getSingleScalarResult()->willReturn(0);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $filters = $this->minimalFilters();
        $filters['dataSource'] = null;

        $result = $this->createProvider()->countBy($filters);

        $this->assertSame(0, $result);
        $queryBuilder->join(Argument::containingString('Route'), Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testAddInternalFiltersSkipsRouteJoinWhenDataSourceIsEmpty(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getSingleScalarResult()->willReturn(0);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $filters = $this->minimalFilters();
        $filters['dataSource'] = '';

        $result = $this->createProvider()->countBy($filters);

        $this->assertSame(0, $result);
        $queryBuilder->join(Argument::containingString('Route'), Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testAddInternalFiltersJoinsRouteWithoutSubfolders(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getSingleScalarResult()->willReturn(3);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $filters = $this->minimalFilters();
        $filters['dataSource'] = 'page-uuid';
        $filters['includeSubFolders'] = false;

        $result = $this->createProvider()->countBy($filters);

        $this->assertSame(3, $result);

        // Assert Route join was called
        $queryBuilder->join(
            Route::class,
            'productRoute',
            Argument::any(),
            Argument::any(),
        )->shouldHaveBeenCalled();

        // Assert parentRoute join was called
        $queryBuilder->join(
            'productRoute.parentRoute',
            'parentRoute',
        )->shouldHaveBeenCalled();

        // Assert direct parent route filter (no subfolders)
        $queryBuilder->andWhere('parentRoute.resourceId = :dataSource')->shouldHaveBeenCalled();
        $queryBuilder->setParameter('dataSource', 'page-uuid')->shouldHaveBeenCalled();

        // Assert page tree joins were NOT used
        $queryBuilder->join(
            PageInterface::class,
            Argument::any(),
            Argument::cetera(),
        )->shouldNotHaveBeenCalled();
    }

    public function testAddInternalFiltersJoinsPageTreeWithSubfolders(): void
    {
        $queryBuilder = $this->createQueryBuilderProphecy();

        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $query->getSingleScalarResult()->willReturn(5);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productRepository->createQueryBuilder('product')->willReturn($queryBuilder->reveal());

        $filters = $this->minimalFilters();
        $filters['dataSource'] = 'page-uuid';
        $filters['includeSubFolders'] = true;

        $result = $this->createProvider()->countBy($filters);

        $this->assertSame(5, $result);

        // Assert Route join was called
        $queryBuilder->join(
            Route::class,
            'productRoute',
            Argument::any(),
            Argument::any(),
        )->shouldHaveBeenCalled();

        // Assert parentRoute join was called
        $queryBuilder->join(
            'productRoute.parentRoute',
            'parentRoute',
        )->shouldHaveBeenCalled();

        // Assert page tree joins for dataSourcePage
        $queryBuilder->join(
            PageInterface::class,
            'dataSourcePage',
            Argument::any(),
            Argument::any(),
        )->shouldHaveBeenCalled();

        // Assert page tree joins for targetPage
        $queryBuilder->join(
            PageInterface::class,
            'targetPage',
            Argument::any(),
            Argument::any(),
        )->shouldHaveBeenCalled();

        // Assert lft/rgt between condition
        $queryBuilder->andWhere('targetPage.lft BETWEEN dataSourcePage.lft AND dataSourcePage.rgt')->shouldHaveBeenCalled();
        $queryBuilder->setParameter('dataSource', 'page-uuid')->shouldHaveBeenCalled();
    }
}
