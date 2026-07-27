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
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductAssociationInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ProductAssociationsDataMapper implements DataMapperInterface
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

        if (!$localizedDimensionContent instanceof ProductDimensionContentInterface) {
            return;
        }

        if (!\array_key_exists('associations', $data)) {
            return;
        }

        $submittedByType = $data['associations'];
        if (!\is_array($submittedByType)) {
            return;
        }

        // Unregistered types are reconciled too, so publishing and copying a locale carry over the rows
        // the normalizer retains. A registry gate cannot tell those apart from freshly submitted ones,
        // because ContentCopier persists normalized source data onto a fresh target. $type stays a list
        // entry rather than an array key, since PHP would coerce a digit-only key back to an int.
        /** @var array<int, array{type: string, targetUuids: array<int, string>}> $submissions */
        $submissions = [];
        /** @var array<int, string> $uuids */
        $uuids = [];
        foreach ($submittedByType as $type => $targetUuids) {
            $type = (string) $type;
            $targetUuids = $this->readTargetUuids($type, $targetUuids);

            $submissions[] = ['type' => $type, 'targetUuids' => $targetUuids];
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

        foreach ($submissions as $submission) {
            $this->reconcileType($localizedDimensionContent, $submission['type'], $submission['targetUuids'], $targets, $sourceUuid);
        }
    }

    /**
     * The generated form schema allows `null` for a type with no selection, so it has to be accepted
     * as an empty list. Non-uuid entries are dropped, like uuids that resolve to no product.
     *
     * @return array<int, string>
     */
    private function readTargetUuids(string $type, mixed $targetUuids): array
    {
        if (null === $targetUuids) {
            return [];
        }

        if (!\is_array($targetUuids)) {
            throw new \InvalidArgumentException(\sprintf(
                'Expected a list of product uuids for association type "%s", got "%s".',
                $type,
                \get_debug_type($targetUuids),
            ));
        }

        $uuids = [];
        foreach ($targetUuids as $targetUuid) {
            if (\is_string($targetUuid)) {
                $uuids[] = $targetUuid;
            }
        }

        return \array_values(\array_unique($uuids));
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
