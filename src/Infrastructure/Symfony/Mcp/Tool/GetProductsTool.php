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
use Sulu\Product\Application\Ai\GetProducts;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

/**
 * @internal
 */
final class GetProductsTool
{
    public function __construct(
        private readonly GetProducts $getProducts,
    ) {
    }

    /**
     * @return array{
     *     results: list<array{code: string, title: string, productFamily: ?string, url: ?string}>,
     *     status: 'ok'|'no_match',
     *     instruction: ?string,
     * }
     */
    #[McpTool(
        name: 'sulu_product_get_products',
        title: 'Search Products by Keyword',
        description: 'Search published products by keyword, article code, or product family — matches product titles and codes as text, nothing else. Never use this for a specification value (an attribute value such as a size, a material, a rating, ...) — those never appear verbatim in a title or code and will always return no_match. Use sulu_product_search_products_by_attributes instead, and sulu_attribute_list to find the exact attribute key.',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW),
    ])]
    public function search(
        string $locale,
        #[Schema(description: 'Free-text search term: an article code or part of a product title, e.g. "ABC-123". Leave empty and pass productFamily instead to list a whole product family.')]
        ?string $query = null,
        #[Schema(description: 'Product family name to narrow the search, or to list on its own. Family names are not known upfront: search by query first, or read the "productFamily" field of a result, to learn one.')]
        ?string $productFamily = null,
        #[Schema(description: 'Whether to include product variants (individual configurations of a product with variants) in the results. Leave false for a product family listing; set true when searching for an exact article code, which may belong to a variant.')]
        bool $includeVariants = false,
        #[Schema(description: 'Maximum number of results to return, capped at 25.')]
        int $limit = 10,
    ): array {
        return ($this->getProducts)($locale, $query, $productFamily, $includeVariants, $limit);
    }
}
