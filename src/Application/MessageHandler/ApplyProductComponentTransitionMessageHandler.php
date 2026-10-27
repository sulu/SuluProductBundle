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

namespace Sulu\Product\Application\MessageHandler;

use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Product\Application\Message\ApplyProductComponentTransitionMessage;
use Sulu\Product\Domain\Event\ProductModifiedEvent;
use Sulu\Product\Domain\Exception\ProductComponentTransitionUnavailableException;
use Sulu\Product\Domain\Model\ProductComponent;
use Sulu\Product\Domain\Model\ProductComponentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * Copies one component row between the draft and the live content of the configurable product.
 * Neither the product nor the component product goes through a workflow transition, because a
 * component row has no workflow of its own.
 *
 * The rows live on the unlocalized content and are shared by all locales. A row can only go live in
 * a locale where the product is live, so the product is never exposed in a locale it was not published in.
 *
 * @internal this class should not be instantiated by a project
 */
final class ApplyProductComponentTransitionMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public function __invoke(ApplyProductComponentTransitionMessage $message): ProductInterface
    {
        $locale = $message->getLocale();
        $componentUuid = $message->getComponentUuid();

        $product = $this->productRepository->getOneBy(
            $message->getIdentifier(),
            [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                'selects' => [],
                'dimensionAttributes' => [
                    'locale' => $locale,
                    'stage' => [DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE],
                ],
            ]],
        );

        $draft = $this->find($product, null, DimensionContentInterface::STAGE_DRAFT);
        $live = $this->find($product, null, DimensionContentInterface::STAGE_LIVE);

        if (null === $draft || null === $live || null === $this->find($product, $locale, DimensionContentInterface::STAGE_LIVE)) {
            throw new ProductComponentTransitionUnavailableException(\sprintf('Product "%s" is not published in "%s".', $product->getUuid(), $locale));
        }

        $draftRow = $this->findRow($draft, $componentUuid);
        $liveRow = $this->findRow($live, $componentUuid);

        if (WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH === $message->getTransitionName()) {
            if (null === $draftRow || null !== $liveRow) {
                throw new ProductComponentTransitionUnavailableException(\sprintf('Component "%s" cannot be published.', $componentUuid));
            }

            $live->addComponent(new ProductComponent($live, $draftRow->getComponent(), $draftRow->getPosition()));
        } else {
            if (null === $liveRow) {
                throw new ProductComponentTransitionUnavailableException(\sprintf('Component "%s" is not published.', $componentUuid));
            }

            $live->removeComponent($liveRow);
        }

        $this->domainEventCollector->collect(new ProductModifiedEvent($product, $locale, [
            'components' => \array_map(
                static fn (ProductComponentInterface $row): string => $row->getComponent()->getUuid(),
                $live->getComponents(),
            ),
        ]));

        return $product;
    }

    private function find(ProductInterface $product, ?string $locale, string $stage): ?ProductDimensionContentInterface
    {
        foreach ($product->getDimensionContents() as $dimensionContent) {
            if ($locale === $dimensionContent->getLocale()
                && $stage === $dimensionContent->getStage()
                && DimensionContentInterface::CURRENT_VERSION === $dimensionContent->getVersion()
            ) {
                return $dimensionContent;
            }
        }

        return null;
    }

    private function findRow(ProductDimensionContentInterface $dimensionContent, string $componentUuid): ?ProductComponentInterface
    {
        foreach ($dimensionContent->getComponents() as $row) {
            if ($row->getComponent()->getUuid() === $componentUuid) {
                return $row;
            }
        }

        return null;
    }
}
