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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Search;

use CmsIg\Seal\Reindex\ReindexConfig;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Sulu\Search\AdminProductReindexProvider;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\AdminProductReindexProviderEnhancerInterface;

#[CoversClass(AdminProductReindexProvider::class)]
class AdminProductReindexProviderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    /** @var ObjectProphecy<EntityRepository<ProductDimensionContentInterface>> */
    private ObjectProphecy $repository;

    /** @var ObjectProphecy<QueryBuilder> */
    private ObjectProphecy $queryBuilder;

    /** @var ObjectProphecy<Query<mixed, mixed>> */
    private ObjectProphecy $query;

    protected function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        /** @var ObjectProphecy<EntityRepository<ProductDimensionContentInterface>> $repository */
        $repository = $this->prophesize(EntityRepository::class);
        $this->repository = $repository;
        $this->queryBuilder = $this->prophesize(QueryBuilder::class);
        /** @var ObjectProphecy<Query<mixed, mixed>> $query */
        $query = $this->prophesize(Query::class);
        $this->query = $query;

        $this->entityManager->getRepository(ProductDimensionContentInterface::class)
            ->willReturn($this->repository->reveal());

        $this->repository->createQueryBuilder('dimensionContent')->willReturn($this->queryBuilder->reveal());
        $this->queryBuilder->select(Argument::any())->willReturn($this->queryBuilder->reveal());
        $this->queryBuilder->addSelect(Argument::any())->willReturn($this->queryBuilder->reveal());
        $this->queryBuilder->where(Argument::any())->willReturn($this->queryBuilder->reveal());
        $this->queryBuilder->andWhere(Argument::any())->willReturn($this->queryBuilder->reveal());
        $this->queryBuilder->setParameter(Argument::cetera())->willReturn($this->queryBuilder->reveal());
        $this->queryBuilder->getQuery()->willReturn($this->query->reveal());
    }

    public function testTotalReturnsNull(): void
    {
        $provider = new AdminProductReindexProvider($this->entityManager->reveal());

        $this->assertNull($provider->total());
    }

    public function testGetIndex(): void
    {
        $this->assertSame('admin', AdminProductReindexProvider::getIndex());
    }

    public function testProvideWithoutData(): void
    {
        $this->query->toIterable()->willReturn([]);

        $provider = new AdminProductReindexProvider($this->entityManager->reveal());
        $reindexConfig = new ReindexConfig();

        $results = \iterator_to_array($provider->provide($reindexConfig));

        $this->assertSame([], $results);
    }

    public function testProvideWithProducts(): void
    {
        $changed = new \DateTimeImmutable('2024-01-01');
        $created = new \DateTimeImmutable('2024-01-02');

        $this->query->toIterable()->willReturn([
            [
                'productId' => 42,
                'changed' => $changed,
                'created' => $created,
                'title' => 'My Product',
                'locale' => 'en',
                'templateKey' => 'default',
            ],
        ]);

        $provider = new AdminProductReindexProvider($this->entityManager->reveal());
        $reindexConfig = new ReindexConfig();

        $results = \iterator_to_array($provider->provide($reindexConfig));

        $this->assertCount(1, $results);
        $this->assertSame(ProductInterface::RESOURCE_KEY . '__42__en', $results[0]['id']);
        $this->assertSame(ProductInterface::RESOURCE_KEY, $results[0]['resourceKey']);
        $this->assertSame('42', $results[0]['resourceId']);
        $this->assertSame('My Product', $results[0]['title']);
        $this->assertSame('en', $results[0]['locale']);
        $this->assertSame(ProductAdmin::SECURITY_CONTEXT, $results[0]['securityContext']);
    }

    public function testProvideRunsEnhancers(): void
    {
        $changed = new \DateTimeImmutable('2024-01-01');
        $created = new \DateTimeImmutable('2024-01-02');

        $this->query->toIterable()->willReturn([
            [
                'productId' => 1,
                'changed' => $changed,
                'created' => $created,
                'title' => 'P',
                'locale' => 'en',
                'templateKey' => 'default',
            ],
        ]);

        $enhancer = $this->prophesize(AdminProductReindexProviderEnhancerInterface::class);
        $enhancer->enhanceQuery(Argument::type(QueryBuilder::class))->shouldBeCalled();
        $enhancer->enhanceDocument(Argument::type('array'), Argument::type('array'))
            ->will(fn (array $args): array => (array) $args[1] + ['enhanced' => true]);

        $provider = new AdminProductReindexProvider(
            $this->entityManager->reveal(),
            [$enhancer->reveal()],
        );

        $results = \iterator_to_array($provider->provide(new ReindexConfig()));

        $this->assertTrue($results[0]['enhanced']);
    }

    public function testProvideFiltersIdentifiers(): void
    {
        $this->query->toIterable()->willReturn([]);

        $provider = new AdminProductReindexProvider($this->entityManager->reveal());
        $reindexConfig = (new ReindexConfig())->withIdentifiers([
            ProductInterface::RESOURCE_KEY . '__42__en',
            'other__99__en',
        ]);

        $results = \iterator_to_array($provider->provide($reindexConfig));

        $this->assertSame([], $results);
    }

    public function testProvideReturnsEarlyWhenNoMatchingIdentifiers(): void
    {
        $provider = new AdminProductReindexProvider($this->entityManager->reveal());
        $reindexConfig = (new ReindexConfig())->withIdentifiers(['other__99__en']);

        $results = \iterator_to_array($provider->provide($reindexConfig));

        $this->assertSame([], $results);
    }
}
