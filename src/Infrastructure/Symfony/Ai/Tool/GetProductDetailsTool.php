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

use Sulu\Product\Application\Ai\GetProductDetails;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_get_product_details',
    description: 'Get the full technical specification sheet for one published product by its exact article code. Use this for every specific spec value on a known product — never invent one. Use sulu_product_get_products first if the exact code is not known.',
)]
final class GetProductDetailsTool
{
    public function __construct(
        private readonly GetProductDetails $getProductDetails,
    ) {
    }

    /**
     * @param string $code exact article code, e.g. "ABC-123", use sulu_product_get_products first if it is not known
     * @param string $locale IETF locale of the request, e.g. "en", "de".
     *
     * @return array{
     *     code: string,
     *     title: string,
     *     url: ?string,
     *     productFamily: ?string,
     *     specGroups: list<array{label: string, attributes: list<array{label: string, value: string}>}>,
     * }
     *
     * @throws \InvalidArgumentException when no published product has this code in this locale
     */
    public function __invoke(string $code, string $locale): array
    {
        return ($this->getProductDetails)($code, $locale);
    }
}
