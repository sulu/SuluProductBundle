<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Application\MessageHandler;

use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Application\ContentWorkflow\ContentWorkflowInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Exception\UnavailableContentTransitionException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * @internal This class should not be instantiated by a project.
 *           Create your own Message and Handler instead.
 */
final class ApplyWorkflowTransitionProductMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ContentWorkflowInterface $contentWorkflow,
        private DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public function __invoke(ApplyWorkflowTransitionProductMessage $message): ProductInterface
    {
        $product = $this->productRepository->getOneBy(
            $message->getIdentifier(),
            $this->getSelects($message->getLocale()),
        );

        if (WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH === $message->getTransitionName()) {
            $this->assertParentIsPublished($product, $message->getLocale());
        }

        $this->applyTransition($product, $message->getLocale(), $message->getTransitionName());

        if (WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH === $message->getTransitionName()
            && $product->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)
        ) {
            $this->unpublishVariants($product, $message->getLocale());
        }

        return $product;
    }

    /**
     * A variant renders its product's content, so it goes offline with its product; publishing the
     * product leaves its variants as they are.
     */
    private function unpublishVariants(ProductInterface $product, string $locale): void
    {
        $variants = $this->productRepository->findBy(['parent' => $product->getUuid()], [], $this->getSelects($locale));

        foreach ($variants as $variant) {
            try {
                $this->applyTransition($variant, $locale, WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH);
            } catch (UnavailableContentTransitionException|ContentNotFoundException) {
                // not published in this locale
            }
        }
    }

    /**
     * A variant renders its product's content, so it can only go live where that product is live.
     */
    private function assertParentIsPublished(ProductInterface $product, string $locale): void
    {
        $parent = $product->getParent();

        if (null === $parent) {
            return;
        }

        $publishedParents = $this->productRepository->countBy([
            'uuid' => $parent->getUuid(),
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ]);

        if (0 === $publishedParents) {
            throw new UnavailableContentTransitionException(\sprintf('A variant can only be published while its product is published in locale "%s".', $locale));
        }
    }

    private function applyTransition(ProductInterface $product, string $locale, string $transitionName): void
    {
        $this->contentWorkflow->apply($product, ['locale' => $locale], $transitionName);

        $this->domainEventCollector->collect(new ProductWorkflowTransitionAppliedEvent($product, $transitionName, $locale));
    }

    /**
     * @return array<string, mixed>
     */
    private function getSelects(string $locale): array
    {
        return [
            ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
                'dimensionAttributes' => [
                    'locale' => $locale,
                    'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
                ],
            ],
        ];
    }
}
