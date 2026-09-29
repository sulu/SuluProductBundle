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

namespace Sulu\Product\Application\Ai;

use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * Get the variants and any configured associations of one published product by its exact
 * article code. Framework-agnostic: Infrastructure\Symfony\Ai\Tool\GetRelatedProductsTool wraps
 * this for symfony/ai-agent.
 */
final class GetRelatedProducts
{
    use ResolvesLiveProductContentTrait;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductAssociationTypeRegistry $associationTypeRegistry,
    ) {
    }

    /**
     * @return array{
     *     variants: list<array{code: string, title: string, url: ?string}>,
     *     associations: array<string, list<array{code: string, title: string, url: ?string}>>,
     * }
     *
     * @throws \InvalidArgumentException when no published product has this code in this locale
     */
    public function __invoke(string $code, string $locale): array
    {
        try {
            $product = $this->productRepository->getOneBy([
                'code' => $code,
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_LIVE,
            ]);
        } catch (ProductNotFoundException) {
            throw new \InvalidArgumentException(\sprintf('No published product found with article code "%s".', $code));
        }

        return [
            'variants' => $this->resolveVariants($product, $locale),
            'associations' => $this->resolveAssociations($product, $locale),
        ];
    }

    /**
     * @return list<array{code: string, title: string, url: ?string}>
     */
    private function resolveVariants(ProductInterface $product, string $locale): array
    {
        if (!$product->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)) {
            return [];
        }

        $variants = [];

        foreach ($this->productRepository->findBy(
            ['parent' => $product->getUuid(), 'locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
            ['position' => 'asc'],
        ) as $variant) {
            $summary = $this->toProductSummary($variant, $locale);

            if (null !== $summary) {
                $variants[] = ['code' => $summary['code'], 'title' => $summary['title'], 'url' => $summary['url']];
            }
        }

        return $variants;
    }

    /**
     * @return array<string, list<array{code: string, title: string, url: ?string}>>
     */
    private function resolveAssociations(ProductInterface $product, string $locale): array
    {
        [, $unlocalized] = $this->findLiveDimensionContents($product, $locale);

        if (null === $unlocalized) {
            return [];
        }

        $associations = [];

        foreach ($this->associationTypeRegistry->getTypes() as $type) {
            $targets = [];

            foreach ($unlocalized->getAssociationsByType($type->getKey()) as $association) {
                $summary = $this->toProductSummary($association->getTarget(), $locale);

                if (null !== $summary) {
                    $targets[] = ['code' => $summary['code'], 'title' => $summary['title'], 'url' => $summary['url']];
                }
            }

            if ([] !== $targets) {
                $associations[$type->getKey()] = $targets;
            }
        }

        return $associations;
    }
}
