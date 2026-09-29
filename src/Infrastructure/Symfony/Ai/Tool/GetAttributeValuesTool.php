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

use Sulu\Product\Application\Ai\GetAttributeValues;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_get_attribute_values',
    description: 'List the actual values seen for one product attribute, most common first — call this before searching by a specification value whenever its exact spelling is unclear, instead of guessing and getting zero results because the data is spelled differently. Call sulu_product_get_attributes first to get the exact attribute key.',
)]
final class GetAttributeValuesTool
{
    public function __construct(
        private readonly GetAttributeValues $getAttributeValues,
    ) {
    }

    /**
     * @param string $key exact attribute key, from sulu_product_get_attributes — not its
     *                    translated name
     * @param string $locale IETF locale of the request, e.g. "en", "de".
     * @param int $limit maximum number of distinct values to return, capped at 30
     *
     * @return array{
     *     values: list<array{value: string, count: int}>,
     *     status: 'ok'|'unknown_attribute',
     *     instruction: ?string,
     * }
     */
    public function __invoke(string $key, string $locale, int $limit = 15): array
    {
        return ($this->getAttributeValues)($key, $locale, $limit);
    }
}
