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

namespace Sulu\Product\Infrastructure\Symfony\Ai\Tool;

use Sulu\Product\Application\Ai\GetRelatedProducts;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_get_related_products',
    description: 'Get the variants and any configured associations (e.g. accessories, alternatives) of one published product by its exact article code.',
)]
final class GetRelatedProductsTool
{
    public function __construct(
        private readonly GetRelatedProducts $getRelatedProducts,
    ) {
    }

    /**
     * @param string $code exact article code, e.g. "ABC-123", use sulu_product_get_products first if it is not known
     * @param string $locale IETF locale of the request, e.g. "en", "de".
     *
     * @return array{
     *     variants: list<array{code: string, title: string, url: ?string}>,
     *     associations: array<string, list<array{code: string, title: string, url: ?string}>>,
     * }
     *
     * @throws \InvalidArgumentException when no published product has this code in this locale
     */
    public function __invoke(string $code, string $locale): array
    {
        return ($this->getRelatedProducts)($code, $locale);
    }
}
