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

namespace Sulu\Product\Application\Mcp;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Exception\InvalidProductAssociationException;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * ProductAssociationsDataMapper silently drops unknown targets and keeps any type name,
 * so an agent would never learn about a typo. This resolver fails loudly instead.
 *
 * @internal
 */
final readonly class ProductAssociationResolver
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductAssociationTypeRegistry $associationTypeRegistry,
    ) {
    }

    /**
     * @param array<array-key, mixed> $associations association type key => list of product UUIDs or codes
     *
     * @return array<string, list<string>> association type key => list of product UUIDs
     *
     * @throws InvalidProductAssociationException
     */
    public function resolve(array $associations, string $locale, ?string $sourceUuid = null): array
    {
        $resolved = [];

        foreach ($associations as $type => $references) {
            $type = (string) $type;

            if (!$this->associationTypeRegistry->has($type)) {
                throw new InvalidProductAssociationException(
                    \sprintf('Unknown association type "%s".', $type),
                    'Use a key returned by sulu_product_association_type_list.',
                );
            }

            if (null === $references) {
                $references = [];
            }

            if (!\is_array($references) || !\array_is_list($references)) {
                throw new InvalidProductAssociationException(
                    \sprintf('Association type "%s" must be a list of product UUIDs or codes.', $type),
                    'Pass e.g. {"accessory": ["<uuid>", "<code>"]}. An empty list removes all products of that type.',
                );
            }

            $uuids = [];
            foreach ($references as $reference) {
                if (!\is_string($reference) || '' === $reference) {
                    throw new InvalidProductAssociationException(
                        \sprintf('Association type "%s" contains an entry that is not a product UUID or code.', $type),
                        'Each entry is the UUID or the code of an existing product. Find them with sulu_product_list.',
                    );
                }

                $uuid = $this->resolveUuid($reference, $locale);

                if ($uuid === $sourceUuid) {
                    throw new InvalidProductAssociationException(
                        \sprintf('A product cannot be associated with itself (type "%s").', $type),
                        'Remove the product from its own associations.',
                    );
                }

                $uuids[$uuid] = $uuid;
            }

            $resolved[$type] = \array_values($uuids);
        }

        return $resolved;
    }

    private function resolveUuid(string $reference, string $locale): string
    {
        $product = $this->productRepository->findOneBy(['uuid' => $reference])
            ?? $this->productRepository->findOneBy([
                'code' => $reference,
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
                // The code is shared by all locales. Without this the lookup misses a product that has no content in $locale yet.
                'loadGhost' => true,
            ]);

        if (null === $product) {
            throw new InvalidProductAssociationException(
                \sprintf('No product with UUID or code "%s".', $reference),
                'Find the product with sulu_product_list first. Create it before associating it.',
            );
        }

        return $product->getUuid();
    }
}
