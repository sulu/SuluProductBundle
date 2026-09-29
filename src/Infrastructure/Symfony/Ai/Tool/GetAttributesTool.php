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

use Sulu\Product\Application\Ai\GetAttributes;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'sulu_product_get_attributes',
    description: 'List the known product specification attributes — their exact key, translated name, type, group and (for an "options" attribute) its possible option values — to build a precise sulu_product_get_attribute_values call instead of guessing an attribute key.',
)]
final class GetAttributesTool
{
    public function __construct(
        private readonly GetAttributes $getAttributes,
    ) {
    }

    /**
     * @param string $locale IETF locale of the request, e.g. "en", "de".
     * @param string|null $group attribute group name, or a substring of it, to list only the
     *                           attributes of that group
     *
     * @return list<array{
     *     key: string,
     *     name: string,
     *     type: string,
     *     group: string,
     *     unit: ?string,
     *     options: list<array{key: string, label: string}>,
     * }>
     */
    public function __invoke(string $locale, ?string $group = null): array
    {
        return ($this->getAttributes)($locale, $group);
    }
}
