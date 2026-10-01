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
use Sulu\Product\Application\Ai\GetAttributeValues;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;

/**
 * @internal
 */
final class AttributeValueListTool
{
    public function __construct(
        private readonly GetAttributeValues $getAttributeValues,
    ) {
    }

    /**
     * @return array{
     *     values: list<array{value: string, searchValue: string, count: int}>,
     *     status: 'ok'|'unknown_attribute',
     *     instruction: ?string,
     * }
     */
    #[McpTool(
        name: 'sulu_attribute_value_list',
        title: 'List Values of a Product Attribute',
        description: 'List the values that published products actually carry for one attribute, most common first. Call this before sulu_product_search_products_by_attributes when the exact spelling of a value is unclear, instead of guessing and getting no results. Each value has a display text and a "searchValue". Pass the "searchValue", not the display text, as a filter value. Get the exact attribute key from sulu_attribute_list.',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(AttributeAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW),
    ])]
    public function listValues(
        #[Schema(description: 'Exact attribute key from sulu_attribute_list (its "key" field, not its "id" or translated name).')]
        string $key,
        string $locale,
        #[Schema(description: 'Maximum number of distinct values to return, capped at 30.')]
        int $limit = 15,
    ): array {
        return ($this->getAttributeValues)($key, $locale, $limit);
    }
}
