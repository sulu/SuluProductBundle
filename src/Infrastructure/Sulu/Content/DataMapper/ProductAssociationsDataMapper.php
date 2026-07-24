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
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductAssociationInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ProductAssociationsDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly ProductAssociationTypeRegistry $associationTypeRegistry,
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

        if (!$localizedDimensionContent instanceof ProductDimensionContentInterface) {
            return;
        }

        if (!\array_key_exists('associations', $data)) {
            return;
        }

        /** @var array<string, array<int, string>> $submittedByType */
        $submittedByType = $data['associations'] ?? [];

        /** @var array<string, array<int, string>> $validByType */
        $validByType = [];
        /** @var array<int, string> $uuids */
        $uuids = [];
        foreach ($submittedByType as $type => $targetUuids) {
            $type = (string) $type;
            if (!$this->associationTypeRegistry->has($type)) {
                continue;
            }

            $targetUuids = \array_values(\array_unique($targetUuids));

            $validByType[$type] = $targetUuids;
            foreach ($targetUuids as $targetUuid) {
                $uuids[] = $targetUuid;
            }
        }

        /** @var array<string, ProductInterface> $targets */
        $targets = [];
        if ([] !== $uuids) {
            foreach ($this->productRepository->findBy(['uuids' => \array_values(\array_unique($uuids))]) as $target) {
                $targets[$target->getUuid()] = $target;
            }
        }

        $sourceUuid = $localizedDimensionContent->getResource()->getUuid();

        foreach ($validByType as $type => $targetUuids) {
            $this->reconcileType($localizedDimensionContent, $type, $targetUuids, $targets, $sourceUuid);
        }
    }

    /**
     * @param array<int, string> $targetUuids
     * @param array<string, ProductInterface> $targets
     */
    private function reconcileType(
        ProductDimensionContentInterface $localizedDimensionContent,
        string $type,
        array $targetUuids,
        array $targets,
        string $sourceUuid,
    ): void {
        /** @var array<int, string> $submittedUuids */
        $submittedUuids = [];
        foreach ($targetUuids as $targetUuid) {
            if ($targetUuid === $sourceUuid) {
                continue;
            }

            if (!isset($targets[$targetUuid])) {
                continue;
            }

            $submittedUuids[] = $targetUuid;
        }

        /** @var array<string, ProductAssociationInterface> $existingByUuid */
        $existingByUuid = [];
        foreach ($localizedDimensionContent->getAssociationsByType($type) as $existing) {
            $existingByUuid[$existing->getTarget()->getUuid()] = $existing;
        }

        foreach ($existingByUuid as $uuid => $existing) {
            if (!\in_array($uuid, $submittedUuids, true)) {
                $localizedDimensionContent->removeAssociation($existing);
                unset($existingByUuid[$uuid]);
            }
        }

        foreach ($submittedUuids as $position => $uuid) {
            // $position is the index into the filtered (deduped, resolved, non-self-ref) list, not the raw submitted index.
            $existing = $existingByUuid[$uuid] ?? null;
            if (null === $existing) {
                $localizedDimensionContent->addAssociation(
                    new ProductAssociation($localizedDimensionContent, $targets[$uuid], $type, $position),
                );

                continue;
            }

            $existing->setPosition($position);
        }
    }
}
