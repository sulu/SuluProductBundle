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

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Mcp\Domain\Security\PermissionRequirement;
use Sulu\Mcp\Domain\Security\RequiresPermission;
use Sulu\Product\Application\Ai\AttributeFilter;
use Sulu\Product\Application\Ai\SearchProductsByAttributes;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

/**
 * @internal
 */
final class SearchProductsByAttributesTool
{
    public function __construct(
        private readonly SearchProductsByAttributes $searchProductsByAttributes,
    ) {
    }

    /**
     * @param list<array{key: string, value: string}> $filters
     *
     * @return array{
     *     results: list<array{code: string, title: string, productFamily: ?string, url: ?string}>,
     *     status: 'ok'|'no_match'|'unknown_attribute',
     *     instruction: ?string,
     * }
     */
    #[McpTool(
        name: 'sulu_product_search_products_by_attributes',
        title: 'Search Products by Attribute',
        description: 'Search published products by one or more specification attribute values, e.g. a rated current or a color — something sulu_product_get_products cannot do since it only matches product titles and codes, not specs. Call sulu_attribute_list first to get the exact attribute keys.',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW),
    ])]
    public function search(
        string $locale,
        #[Schema(
            type: 'array',
            items: [
                'type' => 'object',
                'properties' => [
                    'key' => ['type' => 'string'],
                    'value' => ['type' => 'string'],
                ],
                'required' => ['key', 'value'],
            ],
            description: 'One to five {key, value} pairs, ANDed together — a matching product must have every filter\'s attribute contain its value. "key" is the exact attribute key from sulu_attribute_list (its "key" field, not its "id"), never its translated name. "value" matches as a substring for a text or options attribute, or an exact number for a number attribute when the value itself is numeric — never a comparison, range, or wildcard like "16A or more" or "IP54 or higher", none of which match anything and silently return no results. To find the best match among several values, call this once per plausible literal value instead of describing a threshold.',
        )]
        array $filters,
        #[Schema(description: 'Whether to include product variants (individual configurations of a product with variants) in the results.')]
        bool $includeVariants = false,
        #[Schema(description: 'Maximum number of results to return, capped at 25.')]
        int $limit = 10,
    ): array {
        $attributeFilters = \array_map(
            static fn (array $filter): AttributeFilter => new AttributeFilter($filter['key'], $filter['value']),
            $filters,
        );

        return ($this->searchProductsByAttributes)($locale, $attributeFilters, $includeVariants, $limit);
    }
}
