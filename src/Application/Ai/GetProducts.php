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
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * Search published products by keyword, article code, or product family. Framework-agnostic:
 * Infrastructure\Symfony\Ai\Tool\GetProductsTool wraps this for symfony/ai-agent.
 */
final class GetProducts
{
    use ResolvesLiveProductContentTrait;

    private const MAX_LIMIT = 25;

    private const NO_MATCH_INSTRUCTION = 'No product title or code matched this search '
        . 'term. Do not name, guess, or construct any product code as a fallback. Tell the '
        . 'visitor plainly that no match was found.';

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @return array{
     *     results: list<array{code: string, title: string, productFamily: ?string, url: ?string}>,
     *     status: 'ok'|'no_match',
     *     instruction: ?string,
     * }
     */
    public function __invoke(
        string $locale,
        ?string $query = null,
        ?string $productFamily = null,
        bool $includeVariants = false,
        int $limit = 10,
    ): array {
        $query = null !== $query ? \trim($query) : null;
        $productFamily = null !== $productFamily ? \trim($productFamily) : null;

        if (('' === $query || null === $query) && ('' === $productFamily || null === $productFamily)) {
            return ['results' => [], 'status' => 'no_match', 'instruction' => self::NO_MATCH_INSTRUCTION];
        }

        $filters = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'limit' => \max(1, \min($limit, self::MAX_LIMIT)),
        ];

        if ('' !== $query && null !== $query) {
            $filters['query'] = $query;
        }

        if ('' !== $productFamily && null !== $productFamily) {
            $filters['productFamilyName'] = $productFamily;
        }

        if (!$includeVariants) {
            $filters['excludeTypes'] = [ProductInterface::TYPE_VARIANT];
        }

        $results = [];

        foreach ($this->productRepository->findBy($filters, ['title' => 'asc']) as $product) {
            $row = $this->toProductSummary($product, $locale);

            if (null !== $row) {
                $results[] = $row;
            }
        }

        return [
            'results' => $results,
            'status' => [] === $results ? 'no_match' : 'ok',
            'instruction' => [] === $results ? self::NO_MATCH_INSTRUCTION : null,
        ];
    }
}
