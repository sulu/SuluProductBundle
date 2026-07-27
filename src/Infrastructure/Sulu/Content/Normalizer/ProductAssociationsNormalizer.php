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

namespace Sulu\Product\Infrastructure\Sulu\Content\Normalizer;

use Sulu\Content\Application\ContentNormalizer\Normalizer\NormalizerInterface;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Model\ProductAssociationInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;

class ProductAssociationsNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly ProductAssociationTypeRegistry $associationTypeRegistry,
    ) {
    }

    /**
     * @return string[]
     */
    public function getIgnoredAttributes(object $object): array
    {
        if (!$object instanceof ProductDimensionContentInterface) {
            return [];
        }

        return ['associations'];
    }

    /**
     * @param array<string, mixed> $normalizedData
     *
     * @return array<string, mixed>
     */
    public function enhance(object $object, array $normalizedData): array
    {
        if (!$object instanceof ProductDimensionContentInterface) {
            return $normalizedData;
        }

        // The types are collected in a list rather than as array keys, because PHP would coerce a
        // digit-only type to an int key and it would no longer satisfy the string parameter below.
        /** @var array<int, string> $types */
        $types = [];
        foreach ($this->associationTypeRegistry->getTypes() as $type) {
            $types[] = $type->getKey();
        }

        // Types that are no longer registered are retained, so that publishing and copying a locale
        // carry their rows over.
        foreach ($object->getAssociations() as $association) {
            $type = $association->getType();
            if (!\in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        $map = [];
        foreach ($types as $type) {
            $map[$type] = $this->getUuidsForType($object, $type);
        }

        $normalizedData['associations'] = $map;

        return $normalizedData;
    }

    /**
     * @return string[]
     */
    private function getUuidsForType(ProductDimensionContentInterface $object, string $type): array
    {
        return \array_map(
            static fn (ProductAssociationInterface $association): string => $association->getTarget()->getUuid(),
            $object->getAssociationsByType($type),
        );
    }
}
