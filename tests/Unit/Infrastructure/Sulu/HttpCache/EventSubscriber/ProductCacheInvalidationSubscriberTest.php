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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\HttpCache\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Bundle\TagBundle\Entity\Tag;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\HttpCache\EventSubscriber\ProductCacheInvalidationSubscriber;
use Sulu\Product\Infrastructure\Sulu\Reference\ProductAssociationReferenceCleanupSubscriber;
use Sulu\Route\Application\Routing\Generator\RouteGeneratorInterface;
use Sulu\Route\Domain\Model\Route;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProductCacheInvalidationSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<CacheManagerInterface>
     */
    private ObjectProphecy $cacheManager;

    /**
     * @var ObjectProphecy<RouteRepositoryInterface>
     */
    private ObjectProphecy $routeRepository;

    /**
     * @var ObjectProphecy<ContentAggregatorInterface>
     */
    private ObjectProphecy $contentAggregator;

    /**
     * @var ObjectProphecy<RouteGeneratorInterface>
     */
    private ObjectProphecy $routeGenerator;

    /**
     * @var ObjectProphecy<WebspaceManagerInterface>
     */
    private ObjectProphecy $webspaceManager;

    /**
     * @var ObjectProphecy<ProductRepositoryInterface>
     */
    private ObjectProphecy $productRepository;

    /**
     * @var ObjectProphecy<ReferenceRepositoryInterface>
     */
    private ObjectProphecy $referenceRepository;

    private ProductCacheInvalidationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->cacheManager = $this->prophesize(CacheManagerInterface::class);
        $this->cacheManager->supportsTags()->willReturn(true);
        $this->routeRepository = $this->prophesize(RouteRepositoryInterface::class);
        $this->contentAggregator = $this->prophesize(ContentAggregatorInterface::class);
        $this->routeGenerator = $this->prophesize(RouteGeneratorInterface::class);
        $this->webspaceManager = $this->prophesize(WebspaceManagerInterface::class);
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->referenceRepository = $this->prophesize(ReferenceRepositoryInterface::class);

        $this->subscriber = new ProductCacheInvalidationSubscriber(
            $this->cacheManager->reveal(),
            $this->routeRepository->reveal(),
            $this->contentAggregator->reveal(),
            $this->routeGenerator->reveal(),
            $this->webspaceManager->reveal(),
            $this->productRepository->reveal(),
            $this->referenceRepository->reveal()
        );
    }

    public function testInvalidateTagOnPublish(): void
    {
        $product = new Product('product-uuid-123');

        $event = new ProductWorkflowTransitionAppliedEvent(
            $product,
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            'en'
        );

        $this->routeRepository->findBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => 'product-uuid-123',
            'locale' => 'en',
        ])->willReturn([]);

        $this->contentAggregator->aggregate($product, [
            'locale' => 'en',
            'stage' => 'live',
        ])->willThrow(ContentNotFoundException::class);

        $this->cacheManager->invalidateTag('product-uuid-123')
            ->shouldBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }

    public function testInvalidatePathsOnPublish(): void
    {
        $product = new Product('product-uuid-123');

        $event = new ProductWorkflowTransitionAppliedEvent(
            $product,
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            'en'
        );

        $route1 = new Route(ProductInterface::RESOURCE_KEY, 'product-uuid-123', 'en', '/en/shop/test-product');
        $route2 = new Route(ProductInterface::RESOURCE_KEY, 'product-uuid-123', 'en', '/en/catalog/old-slug');

        $this->routeRepository->findBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => 'product-uuid-123',
            'locale' => 'en',
        ])->willReturn([$route1, $route2]);

        $this->productRepository->findBy([
            'associationTargetUuid' => 'product-uuid-123',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn([]);

        $this->cacheManager->supportsTags()->willReturn(false);
        $localization = $this->prophesize(Localization::class);

        $webspace1 = $this->prophesize(Webspace::class);
        $webspace1->getLocalization('en')->willReturn($localization->reveal());
        $webspace1->getKey()->willReturn('sulu_io');

        $webspace2 = $this->prophesize(Webspace::class);
        $webspace2->getLocalization('en')->willReturn($localization->reveal());
        $webspace2->getKey()->willReturn('shop');

        $webspace3 = $this->prophesize(Webspace::class);
        $webspace3->getLocalization('en')->willReturn(null);

        $webspaceCollection = new WebspaceCollection([
            'sulu_io' => $webspace1->reveal(),
            'shop' => $webspace2->reveal(),
            'other' => $webspace3->reveal(),
        ]);

        $this->webspaceManager->getWebspaceCollection()->willReturn($webspaceCollection);

        $this->contentAggregator->aggregate($product, [
            'locale' => 'en',
            'stage' => 'live',
        ])->willThrow(ContentNotFoundException::class);

        $this->routeGenerator->generate('/en/shop/test-product', 'en', 'sulu_io', UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://sulu.io/en/shop/test-product');
        $this->routeGenerator->generate('/en/shop/test-product', 'en', 'shop', UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://shop.example.com/en/shop/test-product');

        $this->routeGenerator->generate('/en/catalog/old-slug', 'en', 'sulu_io', UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://sulu.io/en/catalog/old-slug');
        $this->routeGenerator->generate('/en/catalog/old-slug', 'en', 'shop', UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://shop.example.com/en/catalog/old-slug');

        $this->cacheManager->invalidateTag('product-uuid-123')
            ->shouldBeCalled();

        $this->cacheManager->invalidatePath('https://sulu.io/en/shop/test-product')
            ->shouldBeCalled();
        $this->cacheManager->invalidatePath('https://shop.example.com/en/shop/test-product')
            ->shouldBeCalled();
        $this->cacheManager->invalidatePath('https://sulu.io/en/catalog/old-slug')
            ->shouldBeCalled();
        $this->cacheManager->invalidatePath('https://shop.example.com/en/catalog/old-slug')
            ->shouldBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }

    public function testInvalidateReferringProductPathsWhenCacheDoesNotSupportTags(): void
    {
        $product = new Product('product-uuid-123');
        $referrer = new Product('referrer-uuid-456');

        $event = new ProductWorkflowTransitionAppliedEvent(
            $product,
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            'en'
        );

        $this->cacheManager->supportsTags()->willReturn(false);

        $this->routeRepository->findBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => 'product-uuid-123',
            'locale' => 'en',
        ])->willReturn([]);

        $referrerRoute = new Route(ProductInterface::RESOURCE_KEY, 'referrer-uuid-456', 'en', '/en/shop/referrer-product');
        $this->routeRepository->findBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => 'referrer-uuid-456',
            'locale' => 'en',
        ])->willReturn([$referrerRoute]);

        $this->productRepository->findBy([
            'associationTargetUuid' => 'product-uuid-123',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn([$referrer]);

        $localization = $this->prophesize(Localization::class);
        $webspace = $this->prophesize(Webspace::class);
        $webspace->getLocalization('en')->willReturn($localization->reveal());
        $webspace->getKey()->willReturn('sulu_io');

        $webspaceCollection = new WebspaceCollection([
            'sulu_io' => $webspace->reveal(),
        ]);

        $this->webspaceManager->getWebspaceCollection()->willReturn($webspaceCollection);

        $this->contentAggregator->aggregate($product, [
            'locale' => 'en',
            'stage' => 'live',
        ])->willThrow(ContentNotFoundException::class);

        $this->routeGenerator->generate('/en/shop/referrer-product', 'en', 'sulu_io', UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://sulu.io/en/shop/referrer-product');

        $this->cacheManager->invalidateTag('product-uuid-123')
            ->shouldBeCalled();
        $this->cacheManager->invalidatePath('https://sulu.io/en/shop/referrer-product')
            ->shouldBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }

    public function testDoesNotQueryReferringProductsWhenCacheSupportsTags(): void
    {
        $product = new Product('product-uuid-123');

        $event = new ProductWorkflowTransitionAppliedEvent(
            $product,
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            'en'
        );

        $this->contentAggregator->aggregate($product, [
            'locale' => 'en',
            'stage' => 'live',
        ])->willThrow(ContentNotFoundException::class);

        $this->cacheManager->invalidateTag('product-uuid-123')
            ->shouldBeCalled();

        $this->productRepository->findBy(Argument::cetera())
            ->shouldNotBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }

    public function testDoesNotInvalidateOnNonPublishTransition(): void
    {
        $product = new Product('product-uuid-999');

        $event = new ProductWorkflowTransitionAppliedEvent(
            $product,
            'request_for_review',
            'en'
        );

        $this->cacheManager->invalidateReference(Argument::cetera())->shouldNotBeCalled();
        $this->cacheManager->invalidatePath(Argument::cetera())->shouldNotBeCalled();
        $this->cacheManager->invalidateTag(Argument::cetera())->shouldNotBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }

    public function testInvalidateOnRemove(): void
    {
        $event = new ProductRemovedEvent(
            'product-uuid-456',
            'Test Product',
            ['locales' => ['en', 'de']]
        );

        $this->cacheManager->invalidateTag('product-uuid-456')
            ->shouldBeCalled();

        $this->subscriber->onProductRemoved($event);
    }

    public function testInvalidateReferringProductPathsOnRemoveWhenCacheDoesNotSupportTags(): void
    {
        $event = new ProductRemovedEvent('product-uuid-456', 'Test Product', ['locales' => ['en']]);

        $this->cacheManager->supportsTags()->willReturn(false);

        $this->referenceRepository->findFlatBy(
            [
                'resourceKey' => ProductInterface::RESOURCE_KEY,
                'resourceId' => 'product-uuid-456',
                'referenceResourceKey' => ProductInterface::RESOURCE_KEY,
            ],
            [],
            ['referenceResourceId', 'referenceLocale'],
            true,
        )->willReturn([
            ['referenceResourceId' => 'referrer-uuid-789', 'referenceLocale' => 'en'],
        ]);

        $referrerRoute = new Route(ProductInterface::RESOURCE_KEY, 'referrer-uuid-789', 'en', '/en/shop/referrer-product');
        $this->routeRepository->findBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => 'referrer-uuid-789',
            'locale' => 'en',
        ])->willReturn([$referrerRoute]);

        $localization = $this->prophesize(Localization::class);
        $webspace = $this->prophesize(Webspace::class);
        $webspace->getLocalization('en')->willReturn($localization->reveal());
        $webspace->getKey()->willReturn('sulu_io');

        $this->webspaceManager->getWebspaceCollection()->willReturn(new WebspaceCollection([
            'sulu_io' => $webspace->reveal(),
        ]));

        $this->routeGenerator->generate('/en/shop/referrer-product', 'en', 'sulu_io', UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://sulu.io/en/shop/referrer-product');

        $this->cacheManager->invalidateTag('product-uuid-456')->shouldBeCalled();
        $this->cacheManager->invalidatePath('https://sulu.io/en/shop/referrer-product')->shouldBeCalled();

        $this->subscriber->onProductRemoved($event);
    }

    public function testDoesNotInvalidatePathsOfTheRemovedProductItselfOnRemove(): void
    {
        $event = new ProductRemovedEvent('product-uuid-456', 'Test Product', ['locales' => ['en']]);

        $this->cacheManager->supportsTags()->willReturn(false);

        $this->referenceRepository->findFlatBy(
            [
                'resourceKey' => ProductInterface::RESOURCE_KEY,
                'resourceId' => 'product-uuid-456',
                'referenceResourceKey' => ProductInterface::RESOURCE_KEY,
            ],
            [],
            ['referenceResourceId', 'referenceLocale'],
            true,
        )->willReturn([
            ['referenceResourceId' => 'product-uuid-456', 'referenceLocale' => 'en'],
        ]);

        $this->cacheManager->invalidateTag('product-uuid-456')->shouldBeCalled();
        $this->routeRepository->findBy(Argument::cetera())->shouldNotBeCalled();
        $this->cacheManager->invalidatePath(Argument::cetera())->shouldNotBeCalled();

        $this->subscriber->onProductRemoved($event);
    }

    public function testDoesNotQueryReferrersOnRemoveWhenCacheSupportsTags(): void
    {
        $event = new ProductRemovedEvent('product-uuid-456', 'Test Product', ['locales' => ['en']]);

        $this->cacheManager->invalidateTag('product-uuid-456')->shouldBeCalled();
        $this->referenceRepository->findFlatBy(Argument::cetera())->shouldNotBeCalled();

        $this->subscriber->onProductRemoved($event);
    }

    public function testInvalidateOnUnpublish(): void
    {
        $product = new Product('product-uuid-789');

        $event = new ProductWorkflowTransitionAppliedEvent(
            $product,
            WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH,
            'en'
        );

        $this->routeRepository->findBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => 'product-uuid-789',
            'locale' => 'en',
        ])->willReturn([]);

        $this->contentAggregator->aggregate($product, [
            'locale' => 'en',
            'stage' => 'live',
        ])->willThrow(ContentNotFoundException::class);

        $this->cacheManager->invalidateTag('product-uuid-789')
            ->shouldBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }

    public function testInvalidateExcerptTagsOnPublish(): void
    {
        $product = new Product('product-uuid-with-tags');

        $tag1 = (new Tag())->setName('Technology');
        $tag2 = (new Tag())->setName('CMS');

        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setExcerptTags([$tag1, $tag2]);

        $event = new ProductWorkflowTransitionAppliedEvent(
            $product,
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            'en'
        );

        $this->routeRepository->findBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => 'product-uuid-with-tags',
            'locale' => 'en',
        ])->willReturn([]);

        $this->contentAggregator->aggregate($product, [
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn($dimensionContent);

        $this->cacheManager->invalidateTag('product-uuid-with-tags')
            ->shouldBeCalled();
        $this->cacheManager->invalidateReference('tag', 'Technology')
            ->shouldBeCalled();
        $this->cacheManager->invalidateReference('tag', 'CMS')
            ->shouldBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }

    public function testInvalidateExcerptCategoriesOnPublish(): void
    {
        $product = new Product('product-uuid-with-categories');

        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setExcerptCategories([
            (new Category())->setId(10),
            (new Category())->setId(20),
        ]);

        $event = new ProductWorkflowTransitionAppliedEvent(
            $product,
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            'en'
        );

        $this->routeRepository->findBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => 'product-uuid-with-categories',
            'locale' => 'en',
        ])->willReturn([]);

        $this->contentAggregator->aggregate($product, [
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn($dimensionContent);

        $this->cacheManager->invalidateTag('product-uuid-with-categories')
            ->shouldBeCalled();
        $this->cacheManager->invalidateReference('category', '10')
            ->shouldBeCalled();
        $this->cacheManager->invalidateReference('category', '20')
            ->shouldBeCalled();

        $this->subscriber->onWorkflowTransition($event);
    }

    public function testWorkflowTransitionSkipsWhenNoCacheManager(): void
    {
        $subscriber = new ProductCacheInvalidationSubscriber(
            null,
            $this->routeRepository->reveal(),
            $this->contentAggregator->reveal(),
            $this->routeGenerator->reveal(),
            $this->webspaceManager->reveal(),
            $this->productRepository->reveal(),
            $this->referenceRepository->reveal()
        );

        $product = new Product('product-uuid-123');
        $event = new ProductWorkflowTransitionAppliedEvent(
            $product,
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            'en'
        );

        // The local $subscriber has null cacheManager; $this->cacheManager is irrelevant here.
        // Test passes if no unexpected Prophecy calls are made.
        $this->expectNotToPerformAssertions();

        $subscriber->onWorkflowTransition($event);
    }

    public function testProductRemovedSkipsWhenNoCacheManager(): void
    {
        $subscriber = new ProductCacheInvalidationSubscriber(
            null,
            $this->routeRepository->reveal(),
            $this->contentAggregator->reveal(),
            $this->routeGenerator->reveal(),
            $this->webspaceManager->reveal(),
            $this->productRepository->reveal(),
            $this->referenceRepository->reveal()
        );

        $event = new ProductRemovedEvent(
            'product-uuid-456',
            'Test Product',
            ['locales' => ['en']]
        );

        // The local $subscriber has null cacheManager; $this->cacheManager is irrelevant here.
        // Test passes if no unexpected Prophecy calls are made.
        $this->expectNotToPerformAssertions();

        $subscriber->onProductRemoved($event);
    }

    public function testInvalidatePathsSkipsWhenLocaleIsNull(): void
    {
        $product = new Product('product-uuid-123');

        /** @var \Prophecy\Prophecy\ObjectProphecy<ProductWorkflowTransitionAppliedEvent> $eventProphecy */
        $eventProphecy = $this->prophesize(ProductWorkflowTransitionAppliedEvent::class);
        $eventProphecy->getWorkflowTransitionName()->willReturn(WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $eventProphecy->getProduct()->willReturn($product);
        $eventProphecy->getResourceLocale()->willReturn(null);

        $this->cacheManager->supportsTags()->willReturn(false);
        $this->cacheManager->invalidateTag('product-uuid-123')->shouldBeCalled();

        // routeRepository->findBy must NOT be called because locale is null
        // contentAggregator->aggregate must NOT be called because locale is null
        // (No predictions set — unexpected calls will cause test failure)

        $this->subscriber->onWorkflowTransition($eventProphecy->reveal());
    }

    public function testInvalidateExcerptSkipsWhenLocaleIsNull(): void
    {
        $product = new Product('product-uuid-123');

        /** @var \Prophecy\Prophecy\ObjectProphecy<ProductWorkflowTransitionAppliedEvent> $eventProphecy */
        $eventProphecy = $this->prophesize(ProductWorkflowTransitionAppliedEvent::class);
        $eventProphecy->getWorkflowTransitionName()->willReturn(WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $eventProphecy->getProduct()->willReturn($product);
        $eventProphecy->getResourceLocale()->willReturn(null);

        $this->cacheManager->supportsTags()->willReturn(true);
        $this->cacheManager->invalidateTag('product-uuid-123')->shouldBeCalled();

        // contentAggregator->aggregate must NOT be called because locale is null
        // (No predictions set — unexpected calls will cause test failure)

        $this->subscriber->onWorkflowTransition($eventProphecy->reveal());
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ProductCacheInvalidationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(ProductWorkflowTransitionAppliedEvent::class, $events);
        $this->assertArrayHasKey(ProductRemovedEvent::class, $events);
    }

    public function testRemovalListenerRunsBeforeTheReferenceCleanupSubscriber(): void
    {
        // The cleanup subscriber deletes the reference records that identify the referring products,
        // so this subscriber has to read them first.
        $listener = ProductCacheInvalidationSubscriber::getSubscribedEvents()[ProductRemovedEvent::class];
        $cleanupListener = ProductAssociationReferenceCleanupSubscriber::getSubscribedEvents()[ProductRemovedEvent::class];

        $this->assertIsArray($listener);
        $this->assertGreaterThan(
            \is_array($cleanupListener) ? $cleanupListener[1] : 0,
            $listener[1],
        );
    }
}
