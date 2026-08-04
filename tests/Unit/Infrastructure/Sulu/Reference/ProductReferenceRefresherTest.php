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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Reference;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ReferenceBundle\Domain\Model\ReferenceInterface;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Bundle\TestBundle\Testing\SetGetPrivatePropertyTrait;
use Sulu\Content\Application\ContentMerger\ContentMergerInterface;
use Sulu\Content\Application\ContentResolver\ContentViewResolver\ContentViewResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ContentResolver\Value\Reference;
use Sulu\Content\Domain\Model\DimensionContentCollection;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Infrastructure\Sulu\Reference\ProductReferenceRefresher;

class ProductReferenceRefresherTest extends TestCase
{
    use ProphecyTrait;
    use SetGetPrivatePropertyTrait;

    private ProductReferenceRefresher $refresher;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    /** @var ObjectProphecy<ReferenceRepositoryInterface> */
    private ObjectProphecy $referenceRepository;

    /** @var ObjectProphecy<ContentViewResolverInterface> */
    private ObjectProphecy $contentViewResolver;

    /** @var ObjectProphecy<ContentMergerInterface> */
    private ObjectProphecy $contentMerger;

    /** @var ObjectProphecy<EntityRepository<ProductDimensionContentInterface>> */
    private ObjectProphecy $productDimensionContentRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->referenceRepository = $this->prophesize(ReferenceRepositoryInterface::class);
        $this->contentViewResolver = $this->prophesize(ContentViewResolverInterface::class);
        $this->contentMerger = $this->prophesize(ContentMergerInterface::class);
        /** @var ObjectProphecy<EntityRepository<ProductDimensionContentInterface>> $prophecy */
        $prophecy = $this->prophesize(EntityRepository::class);
        $this->productDimensionContentRepository = $prophecy;

        $this->entityManager->getRepository(ProductDimensionContentInterface::class)
            ->willReturn($this->productDimensionContentRepository->reveal());

        $this->refresher = new ProductReferenceRefresher(
            $this->entityManager->reveal(),
            $this->referenceRepository->reveal(),
            $this->contentViewResolver->reveal(),
            $this->contentMerger->reveal(),
        );
    }

    public function testGetResourceKeyMatchesTheKeyTheRefreshersAreIndexedBy(): void
    {
        $this->assertSame(ProductDimensionContentInterface::RESOURCE_KEY, ProductReferenceRefresher::getResourceKey());
    }

    public function testRefreshReturnsGenerator(): void
    {
        $queryBuilder = $this->prophesize(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->prophesize(\Doctrine\ORM\Query::class);

        $queryBuilder->where('dimensionContent.version = :version')->willReturn($queryBuilder);
        $queryBuilder->setParameter('version', DimensionContentInterface::CURRENT_VERSION)->willReturn($queryBuilder);
        $queryBuilder->orderBy('dimensionContent.product', 'ASC')->willReturn($queryBuilder);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $query->toIterable()->willReturn(new \ArrayIterator([]));

        $this->productDimensionContentRepository->createQueryBuilder('dimensionContent')
            ->willReturn($queryBuilder->reveal());

        $generator = $this->refresher->refresh();

        $results = \iterator_to_array($generator);
        $this->assertEmpty($results);
    }

    public function testRefreshWithFilter(): void
    {
        $filter = ['resourceId' => '123', 'resourceKey' => 'products', 'locale' => 'en', 'stage' => 'live'];

        $queryBuilder = $this->prophesize(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->prophesize(\Doctrine\ORM\Query::class);

        $queryBuilder->where('dimensionContent.version = :version')->willReturn($queryBuilder);
        $queryBuilder->setParameter('version', DimensionContentInterface::CURRENT_VERSION)->willReturn($queryBuilder);
        $queryBuilder->orderBy('dimensionContent.product', 'ASC')->willReturn($queryBuilder);
        $queryBuilder->join('dimensionContent.product', 'product', \Doctrine\ORM\Query\Expr\Join::WITH, 'product.uuid = :resourceId')->willReturn($queryBuilder);
        $queryBuilder->andWhere('dimensionContent.locale = :locale OR dimensionContent.locale IS NULL')->willReturn($queryBuilder);
        $queryBuilder->andWhere('dimensionContent.stage = :stage')->willReturn($queryBuilder);
        $queryBuilder->setParameter('resourceId', '123')->willReturn($queryBuilder);
        $queryBuilder->setParameter('locale', 'en')->willReturn($queryBuilder);
        $queryBuilder->setParameter('stage', 'live')->willReturn($queryBuilder);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $query->toIterable()->willReturn(new \ArrayIterator([]));

        $this->productDimensionContentRepository->createQueryBuilder('dimensionContent')
            ->willReturn($queryBuilder->reveal());

        $generator = $this->refresher->refresh($filter);
        $results = \iterator_to_array($generator);

        $this->assertEmpty($results);
    }

    /**
     * Helper that stubs the no-filter QueryBuilder chain and returns a configured query prophecy.
     *
     * @return ObjectProphecy<\Doctrine\ORM\Query<mixed, mixed>>
     */
    private function stubQueryBuilderNoFilter(): ObjectProphecy
    {
        $queryBuilder = $this->prophesize(\Doctrine\ORM\QueryBuilder::class);
        /** @var ObjectProphecy<\Doctrine\ORM\Query<mixed, mixed>> $query */
        $query = $this->prophesize(\Doctrine\ORM\Query::class);

        $queryBuilder->where('dimensionContent.version = :version')->willReturn($queryBuilder);
        $queryBuilder->setParameter('version', DimensionContentInterface::CURRENT_VERSION)->willReturn($queryBuilder);
        $queryBuilder->orderBy('dimensionContent.product', 'ASC')->willReturn($queryBuilder);
        $queryBuilder->getQuery()->willReturn($query->reveal());

        $this->productDimensionContentRepository->createQueryBuilder('dimensionContent')
            ->willReturn($queryBuilder->reveal());

        return $query;
    }

    public function testRefreshWithSingleProductYieldsOnce(): void
    {
        $product = new Product('product-uuid-1');
        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('live');

        $query = $this->stubQueryBuilderNoFilter();
        $query->toIterable()->willReturn(new \ArrayIterator([$dimensionContent]));

        $this->contentMerger->merge(Argument::type(DimensionContentCollection::class))
            ->willReturn($dimensionContent);

        $this->contentViewResolver->getContentViews(Argument::any())
            ->willReturn([]);

        // ReferenceCollector::persistReferences() calls removeBy then add (none here)
        $this->referenceRepository->removeBy(Argument::cetera());

        $results = \iterator_to_array($this->refresher->refresh());

        $this->assertCount(1, $results);
        $this->assertSame($dimensionContent, $results[0]);
    }

    public function testRefreshWithTwoProductsProcessesGroupChange(): void
    {
        $product1 = new Product('uuid-1');
        $dc1 = new ProductDimensionContent($product1);
        $dc1->setLocale('en');
        $dc1->setStage('live');

        $product2 = new Product('uuid-2');
        $dc2 = new ProductDimensionContent($product2);
        $dc2->setLocale('en');
        $dc2->setStage('live');

        $query = $this->stubQueryBuilderNoFilter();
        $query->toIterable()->willReturn(new \ArrayIterator([$dc1, $dc2]));

        // merge is called once per group (two groups = two calls)
        // Use willReturn with a sequence so the first call returns dc1, second returns dc2
        $callCount = 0;
        $this->contentMerger->merge(Argument::type(DimensionContentCollection::class))
            ->will(function() use ($dc1, $dc2, &$callCount): ProductDimensionContent {
                return 0 === $callCount++ ? $dc1 : $dc2;
            });

        $this->contentViewResolver->getContentViews(Argument::any())
            ->willReturn([]);

        $this->referenceRepository->removeBy(Argument::cetera());

        $results = \iterator_to_array($this->refresher->refresh());

        $this->assertCount(2, $results);
    }

    public function testRefreshWithMultipleStagesResetsUnlocalizedContent(): void
    {
        $product = new Product('uuid-multi-stage');

        $unlocalizedDraft = new ProductDimensionContent($product);
        // locale stays null (unlocalized)
        $unlocalizedDraft->setStage('draft');

        $enDraft = new ProductDimensionContent($product);
        $enDraft->setLocale('en');
        $enDraft->setStage('draft');

        $enLive = new ProductDimensionContent($product);
        $enLive->setLocale('en');
        $enLive->setStage('live');

        $query = $this->stubQueryBuilderNoFilter();
        $query->toIterable()->willReturn(new \ArrayIterator([$unlocalizedDraft, $enDraft, $enLive]));

        // contentMerger is called once per localized dimension content (en/draft and en/live)
        $callCount = 0;
        $this->contentMerger->merge(Argument::type(DimensionContentCollection::class))
            ->will(function() use ($enDraft, $enLive, &$callCount): ProductDimensionContent {
                return 0 === $callCount++ ? $enDraft : $enLive;
            });

        $this->contentViewResolver->getContentViews(Argument::any())
            ->willReturn([]);

        $this->referenceRepository->removeBy(Argument::cetera());

        $results = \iterator_to_array($this->refresher->refresh());

        $this->assertCount(2, $results);
    }

    public function testRefreshWithContentViewsAddsReferences(): void
    {
        $product = new Product('product-uuid-ref');
        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('live');
        $dimensionContent->setTemplateKey('product');

        $query = $this->stubQueryBuilderNoFilter();
        $query->toIterable()->willReturn(new \ArrayIterator([$dimensionContent]));

        $this->contentMerger->merge(Argument::type(DimensionContentCollection::class))
            ->willReturn($dimensionContent);

        // Build a real ContentView with one reference
        $reference = new Reference('42', 'media', 'image');
        $contentView = ContentView::createWithReferences(null, [], [$reference]);

        $this->contentViewResolver->getContentViews(Argument::any())
            ->willReturn(['template' => $contentView]);

        // ReferenceCollector::addReference calls referenceRepository->create(...)
        /** @var ObjectProphecy<ReferenceInterface> $referenceModel */
        $referenceModel = $this->prophesize(ReferenceInterface::class);
        $referenceModel->equals(Argument::any())->willReturn(false);

        $this->referenceRepository->create(
            Argument::type('string'),
            Argument::type('string'),
            Argument::type('string'),
            Argument::type('string'),
            Argument::type('string'),
            Argument::type('string'),
            Argument::type('string'),
            Argument::type('string'),
            Argument::type('array'),
        )->willReturn($referenceModel->reveal());

        $this->referenceRepository->removeBy(Argument::cetera());
        $this->referenceRepository->add(Argument::type(ReferenceInterface::class));

        \iterator_to_array($this->refresher->refresh());

        $this->referenceRepository->add(Argument::type(ReferenceInterface::class))
            ->shouldHaveBeenCalledOnce();
    }
}
