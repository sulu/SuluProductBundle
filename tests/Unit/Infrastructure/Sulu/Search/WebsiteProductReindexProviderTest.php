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
use Sulu\Product\Domain\Model\ProductDimensionContentAdditionalWebspace;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductReindexProviderEnhancerInterface;
use Sulu\Product\Infrastructure\Sulu\Search\WebsiteProductReindexProvider;

#[CoversClass(WebsiteProductReindexProvider::class)]
class WebsiteProductReindexProviderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    /** @var ObjectProphecy<EntityRepository<ProductDimensionContentInterface>> */
    private ObjectProphecy $dimensionContentRepository;

    /** @var ObjectProphecy<EntityRepository<ProductDimensionContentAdditionalWebspace>> */
    private ObjectProphecy $additionalWebspacesRepository;

    /** @var ObjectProphecy<QueryBuilder> */
    private ObjectProphecy $dimensionContentQb;

    /** @var ObjectProphecy<QueryBuilder> */
    private ObjectProphecy $additionalQb;

    /** @var ObjectProphecy<Query<mixed, mixed>> */
    private ObjectProphecy $dimensionQuery;

    /** @var ObjectProphecy<Query<mixed, mixed>> */
    private ObjectProphecy $additionalQuery;

    protected function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        /** @var ObjectProphecy<EntityRepository<ProductDimensionContentInterface>> $dimensionContentRepository */
        $dimensionContentRepository = $this->prophesize(EntityRepository::class);
        $this->dimensionContentRepository = $dimensionContentRepository;
        /** @var ObjectProphecy<EntityRepository<ProductDimensionContentAdditionalWebspace>> $additionalWebspacesRepository */
        $additionalWebspacesRepository = $this->prophesize(EntityRepository::class);
        $this->additionalWebspacesRepository = $additionalWebspacesRepository;

        $this->dimensionContentQb = $this->prophesize(QueryBuilder::class);
        $this->additionalQb = $this->prophesize(QueryBuilder::class);
        /** @var ObjectProphecy<Query<mixed, mixed>> $dimensionQuery */
        $dimensionQuery = $this->prophesize(Query::class);
        $this->dimensionQuery = $dimensionQuery;
        /** @var ObjectProphecy<Query<mixed, mixed>> $additionalQuery */
        $additionalQuery = $this->prophesize(Query::class);
        $this->additionalQuery = $additionalQuery;

        $this->entityManager->getRepository(ProductDimensionContentInterface::class)
            ->willReturn($this->dimensionContentRepository->reveal());
        $this->entityManager->getRepository(ProductDimensionContentAdditionalWebspace::class)
            ->willReturn($this->additionalWebspacesRepository->reveal());

        $this->dimensionContentRepository->createQueryBuilder('dimensionContent')
            ->willReturn($this->dimensionContentQb->reveal());
        $this->additionalWebspacesRepository->createQueryBuilder('additionalWebspace')
            ->willReturn($this->additionalQb->reveal());

        foreach (['select', 'addSelect', 'where', 'andWhere', 'leftJoin', 'orderBy'] as $method) {
            $this->dimensionContentQb->$method(Argument::cetera())->willReturn($this->dimensionContentQb->reveal());
            $this->additionalQb->$method(Argument::cetera())->willReturn($this->additionalQb->reveal());
        }
        $this->dimensionContentQb->setParameter(Argument::cetera())->willReturn($this->dimensionContentQb->reveal());
        $this->dimensionContentQb->setFirstResult(Argument::any())->willReturn($this->dimensionContentQb->reveal());
        $this->dimensionContentQb->setMaxResults(Argument::any())->willReturn($this->dimensionContentQb->reveal());
        $this->dimensionContentQb->getQuery()->willReturn($this->dimensionQuery->reveal());

        $this->additionalQb->setParameter(Argument::cetera())->willReturn($this->additionalQb->reveal());
        $this->additionalQb->getQuery()->willReturn($this->additionalQuery->reveal());
    }

    public function testTotalReturnsNull(): void
    {
        $provider = new WebsiteProductReindexProvider($this->entityManager->reveal());

        $this->assertNull($provider->total());
    }

    public function testGetIndex(): void
    {
        $this->assertSame('website', WebsiteProductReindexProvider::getIndex());
    }

    public function testProvideEmptyResultStopsIteration(): void
    {
        $this->dimensionQuery->getResult()->willReturn([]);

        $provider = new WebsiteProductReindexProvider($this->entityManager->reveal());

        $results = \iterator_to_array($provider->provide(new ReindexConfig()));

        $this->assertSame([], $results);
    }

    public function testProvideYieldsDocumentsForBatch(): void
    {
        // The first call returns a single batch, every subsequent call returns
        // an empty batch so the provider's pagination loop terminates. This must
        // use willReturn() with consecutive values rather than a closure with a
        // static counter: Prophecy does not preserve closure static state across
        // calls under PHPUnit, so the counter would stay at 1 and loop forever.
        $this->dimensionQuery->getResult()->willReturn(
            [
                [
                    'productId' => 42,
                    'authored' => new \DateTimeImmutable('2024-01-01'),
                    'changed' => new \DateTimeImmutable('2024-01-02'),
                    'title' => 'Sample',
                    'locale' => 'en',
                    'mainWebspace' => 'main',
                    'dimensionContentId' => 7,
                    'slug' => '/sample',
                ],
            ],
            [],
        );

        $this->additionalQuery->getResult()->willReturn([
            ['productDimensionContentId' => 7, 'webspace' => 'extra'],
        ]);

        $provider = new WebsiteProductReindexProvider($this->entityManager->reveal());

        $results = \iterator_to_array($provider->provide(new ReindexConfig()));

        $this->assertCount(1, $results);
        $this->assertSame(ProductInterface::RESOURCE_KEY . '__42__en', $results[0]['id']);
        $this->assertSame('Sample', $results[0]['title']);
        $this->assertSame('/sample', $results[0]['url']);
        $this->assertSame(['main', 'extra'], $results[0]['webspaces']);
    }

    public function testProvideRunsEnhancers(): void
    {
        $this->dimensionQuery->getResult()->willReturn(
            [
                [
                    'productId' => 1,
                    'authored' => null,
                    'changed' => new \DateTimeImmutable('2024-01-02'),
                    'title' => 'T',
                    'locale' => 'en',
                    'mainWebspace' => null,
                    'dimensionContentId' => 9,
                    'slug' => '/s',
                ],
            ],
            [],
        );

        $this->additionalQuery->getResult()->willReturn([]);

        $enhancer = $this->prophesize(WebsiteProductReindexProviderEnhancerInterface::class);
        $enhancer->enhanceQuery(Argument::type(QueryBuilder::class))->shouldBeCalled();
        $enhancer->enhanceDocument(Argument::type('array'), Argument::type('array'))
            ->will(fn (array $args): array => (array) $args[1] + ['enhanced' => true]);

        $provider = new WebsiteProductReindexProvider(
            $this->entityManager->reveal(),
            [$enhancer->reveal()],
        );

        $results = \iterator_to_array($provider->provide(new ReindexConfig()));

        $this->assertTrue($results[0]['enhanced']);
    }

    public function testProvideReturnsEmptyWhenNoMatchingIdentifiers(): void
    {
        $provider = new WebsiteProductReindexProvider($this->entityManager->reveal());
        $reindexConfig = (new ReindexConfig())->withIdentifiers(['other__99__en']);

        $results = \iterator_to_array($provider->provide($reindexConfig));

        $this->assertSame([], $results);
    }

    public function testProvideFiltersByMatchingIdentifiers(): void
    {
        // Mix of a matching product identifier and a non-matching one. The
        // matching identifier exercises the condition-building branch and the
        // andWhere() call inside loadBatch().
        $this->dimensionQuery->getResult()->willReturn(
            [
                [
                    'productId' => 99,
                    'authored' => new \DateTimeImmutable('2024-01-01'),
                    'changed' => new \DateTimeImmutable('2024-01-02'),
                    'title' => 'Match',
                    'locale' => 'en',
                    'mainWebspace' => 'main',
                    'dimensionContentId' => 5,
                    'slug' => '/match',
                ],
            ],
            [],
        );
        $this->additionalQuery->getResult()->willReturn([]);

        $provider = new WebsiteProductReindexProvider($this->entityManager->reveal());
        $reindexConfig = (new ReindexConfig())->withIdentifiers([ProductInterface::RESOURCE_KEY . '__99__en', 'other__1__en']);

        $results = \iterator_to_array($provider->provide($reindexConfig));

        $this->assertCount(1, $results);
        $this->assertSame(ProductInterface::RESOURCE_KEY . '__99__en', $results[0]['id']);
    }

    public function testProvideWithBatchMissingDimensionContentIds(): void
    {
        // Batch row without a dimensionContentId column makes array_column()
        // return an empty list, exercising the early return in
        // loadAdditionalWebspaces().
        $this->dimensionQuery->getResult()->willReturn(
            [
                [
                    'productId' => 7,
                    'authored' => new \DateTimeImmutable('2024-01-01'),
                    'changed' => new \DateTimeImmutable('2024-01-02'),
                    'title' => 'NoDimension',
                    'locale' => 'en',
                    'mainWebspace' => 'main',
                    'slug' => '/no-dimension',
                ],
            ],
            [],
        );

        $this->additionalQb->getQuery()->shouldNotBeCalled();

        $provider = new WebsiteProductReindexProvider($this->entityManager->reveal());

        $results = \iterator_to_array($provider->provide(new ReindexConfig()));

        $this->assertCount(1, $results);
        $this->assertSame(['main'], $results[0]['webspaces']);
    }
}
