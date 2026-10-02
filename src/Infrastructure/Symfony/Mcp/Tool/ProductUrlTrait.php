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

namespace Sulu\Product\Infrastructure\Symfony\Mcp\Tool;

use Sulu\Product\Application\Mcp\ProductCompletenessChecker;

/**
 * The product route is only created when the saved data carries a "url". Without it the product
 * has no page on the website, and nothing fails to tell the agent.
 *
 * @internal
 */
trait ProductUrlTrait
{
    private const URL_WARNING = 'The product has no url, so it has no route and no page on the website. Set "url" with sulu_product_update before publishing: {"page": {"uuid": "<page uuid>", "path": "<page path>"}, "suffix": "/<slug>"}. Copy the page from a sibling product (sulu_product_get).';

    /**
     * Fills a missing suffix of a page based url from the title, so that the agent only has to name the page.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function completeUrlSuffix(array $data): array
    {
        $url = $data['url'] ?? null;
        $title = $data['title'] ?? null;
        if (!\is_array($url) || !\is_string($title) || !\is_array($url['page'] ?? null)) {
            return $data;
        }

        $suffix = $url['suffix'] ?? null;
        if (\is_string($suffix) && '' !== \trim($suffix, '/ ')) {
            return $data;
        }

        $ascii = \transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $title);
        $slug = \trim((string) \preg_replace('/[^a-z0-9]+/', '-', \is_string($ascii) ? $ascii : ''), '-');
        if ('' === $slug) {
            return $data;
        }

        $url['suffix'] = '/' . $slug;
        $data['url'] = $url;

        return $data;
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function hasProductUrl(array $normalized): bool
    {
        return ProductCompletenessChecker::hasUrl($normalized);
    }
}
