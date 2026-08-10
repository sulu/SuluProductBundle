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

namespace Sulu\Product\Infrastructure\Sulu\HttpCache\EventSubscriber;

use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Route\Application\Routing\Generator\RouteGeneratorInterface;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal No BC promise is given for this class. Create your own event subscriber or use the
 * Symfony DependencyInjection container to override this service.
 */
class ProductCacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ?CacheManagerInterface $cacheManager,
        private RouteRepositoryInterface $routeRepository,
        private ContentAggregatorInterface $contentAggregator,
        private RouteGeneratorInterface $routeGenerator,
        private WebspaceManagerInterface $webspaceManager,
        private ProductRepositoryInterface $productRepository,
        private ReferenceRepositoryInterface $referenceRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductWorkflowTransitionAppliedEvent::class => 'onWorkflowTransition',
            // Must run before ProductAssociationReferenceCleanupSubscriber deletes those records.
            ProductRemovedEvent::class => ['onProductRemoved', 10],
        ];
    }

    public function onWorkflowTransition(ProductWorkflowTransitionAppliedEvent $event): void
    {
        if (!$this->cacheManager) {
            return;
        }

        if (!\in_array($event->getWorkflowTransitionName(), [
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH,
        ])) {
            return;
        }

        $product = $event->getProduct();

        $this->cacheManager->invalidateTag($product->getUuid());

        if (!$this->cacheManager->supportsTags()) {
            $this->invalidateProductPaths($product->getUuid(), $event->getResourceLocale());
            $this->invalidateReferringProductPaths($product, $event->getResourceLocale());
        }

        $this->invalidateProductExcerpt($product, $event->getResourceLocale());
    }

    public function onProductRemoved(ProductRemovedEvent $event): void
    {
        if (!$this->cacheManager) {
            return;
        }

        $this->cacheManager->invalidateTag($event->getResourceId());

        if (!$this->cacheManager->supportsTags()) {
            $this->invalidateReferringProductPathsOfRemovedProduct($event->getResourceId());
        }
    }

    /**
     * ProductAssociation rows are already gone by the time this runs post-flush, because the target
     * join column cascades on delete. Reference records still point at the removed product.
     */
    private function invalidateReferringProductPathsOfRemovedProduct(string $removedProductUuid): void
    {
        /** @var iterable<array{referenceResourceId: string, referenceLocale: string}> $referrers */
        $referrers = $this->referenceRepository->findFlatBy(
            filters: [
                'resourceKey' => ProductInterface::RESOURCE_KEY,
                'resourceId' => $removedProductUuid,
                'referenceResourceKey' => ProductInterface::RESOURCE_KEY,
            ],
            fields: ['referenceResourceId', 'referenceLocale'],
            distinct: true,
        );

        foreach ($referrers as $referrer) {
            if ($removedProductUuid === $referrer['referenceResourceId']) {
                continue;
            }

            $this->invalidateProductPaths($referrer['referenceResourceId'], $referrer['referenceLocale']);
        }
    }

    private function invalidateProductPaths(string $productUuid, ?string $locale): void
    {
        if (!$locale || !$this->cacheManager) {
            return;
        }

        $routes = $this->routeRepository->findBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => $productUuid,
            'locale' => $locale,
        ]);

        /** @var Webspace $webspace */
        foreach ($this->webspaceManager->getWebspaceCollection() as $webspace) {
            if (null === $webspace->getLocalization($locale)) {
                continue;
            }

            $webspaceKey = $webspace->getKey();

            foreach ($routes as $route) {
                $url = $this->routeGenerator->generate(
                    $route->getSlug(),
                    $route->getLocale(),
                    $webspaceKey,
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $this->cacheManager->invalidatePath($url);
            }
        }
    }

    private function invalidateReferringProductPaths(ProductInterface $product, ?string $locale): void
    {
        if (!$locale || !$this->cacheManager) {
            return;
        }

        $referrers = $this->productRepository->findBy([
            'associationTargetUuid' => $product->getUuid(),
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ]);

        foreach ($referrers as $referrer) {
            $this->invalidateProductPaths($referrer->getUuid(), $locale);
        }
    }

    private function invalidateProductExcerpt(ProductInterface $product, ?string $locale): void
    {
        if (!$locale || !$this->cacheManager) {
            return;
        }

        try {
            /** @var ProductDimensionContentInterface $dimensionContent */
            $dimensionContent = $this->contentAggregator->aggregate($product, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ]);
        } catch (ContentNotFoundException) {
            return;
        }

        foreach ($dimensionContent->getExcerptTags() as $tag) {
            $this->cacheManager->invalidateReference('tag', $tag->getName());
        }

        foreach ($dimensionContent->getExcerptCategoryIds() as $categoryId) {
            $this->cacheManager->invalidateReference('category', (string) $categoryId);
        }
    }
}
