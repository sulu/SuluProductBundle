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

namespace Sulu\Product\Infrastructure\Sulu\Content\DataMapper;

use Sulu\Content\Application\ContentDataMapper\DataMapper\DataMapperInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductComponent;
use Sulu\Product\Domain\Model\ProductComponentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ProductComponentsDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function map(
        DimensionContentInterface $unlocalizedDimensionContent,
        DimensionContentInterface $localizedDimensionContent,
        array $data,
    ): void {
        if (!$unlocalizedDimensionContent instanceof ProductDimensionContentInterface) {
            return;
        }

        // The live rows are only changed by publishing or unpublishing a row. Publishing the product copies
        // its draft to the live content through this mapper and must not touch them.
        if (DimensionContentInterface::STAGE_DRAFT !== $unlocalizedDimensionContent->getStage()) {
            return;
        }

        if (!\array_key_exists('components', $data)) {
            return;
        }

        $submitted = $data['components'];
        if (null !== $submitted && !\is_array($submitted)) {
            throw new \InvalidArgumentException(\sprintf(
                'Expected a list of product uuids for the components, got "%s".',
                \get_debug_type($submitted),
            ));
        }

        $uuids = [];
        foreach ($submitted ?? [] as $uuid) {
            if (\is_string($uuid)) {
                $uuids[] = $uuid;
            }
        }

        /** @var array<string, ProductInterface> $targets */
        $targets = [];
        if ([] !== $uuids) {
            foreach ($this->productRepository->findBy(['uuids' => \array_values(\array_unique($uuids))]) as $target) {
                $targets[$target->getUuid()] = $target;
            }
        }

        $sourceUuid = $unlocalizedDimensionContent->getResource()->getUuid();

        // Unknown uuids and the product itself are dropped. A product may appear more than once,
        // because rules will later attach to the single rows.
        $submittedUuids = [];
        foreach ($uuids as $uuid) {
            if ($uuid !== $sourceUuid && isset($targets[$uuid])) {
                $submittedUuids[] = $uuid;
            }
        }

        // Existing rows are reused per product in their order, so a save keeps the ids that rules point at.
        /** @var array<string, array<int, ProductComponentInterface>> $existingByUuid */
        $existingByUuid = [];
        foreach ($unlocalizedDimensionContent->getComponents() as $existing) {
            $existingByUuid[$existing->getComponent()->getUuid()][] = $existing;
        }

        foreach ($submittedUuids as $position => $uuid) {
            $existing = isset($existingByUuid[$uuid]) ? \array_shift($existingByUuid[$uuid]) : null;
            if (null === $existing) {
                $unlocalizedDimensionContent->addComponent(
                    new ProductComponent($unlocalizedDimensionContent, $targets[$uuid], $position),
                );

                continue;
            }

            $existing->setPosition($position);
        }

        foreach ($existingByUuid as $unused) {
            foreach ($unused as $existing) {
                $unlocalizedDimensionContent->removeComponent($existing);
            }
        }
    }
}
