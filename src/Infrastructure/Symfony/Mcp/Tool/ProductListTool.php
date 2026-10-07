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
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Mcp\Domain\Security\PermissionRequirement;
use Sulu\Mcp\Domain\Security\RequiresPermission;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

/**
 * @internal
 */
final class ProductListTool
{
    private const SUMMARY_FIELDS = [
        'title', 'code', 'status', 'productFamily', 'template',
        'url', 'locale', 'stage',
        'published', 'publishedState', 'workflowPlace',
        'created', 'changed',
        'availableLocales', 'contentLocales', 'ghostLocale',
    ];

    private const ALLOWED_SORT_FIELDS = ['title', 'created', 'changed'];
    private const ALLOWED_SORT_ORDERS = ['asc', 'desc'];

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_product_list',
        title: 'List Products',
        description: 'List products with optional filters. Returns lightweight summaries (title, code, status, family, workflow state). Use sulu_product_get for a single product\'s full data. Variants are excluded by default because they belong to their parent; set includeVariants=true to list them too, or use sulu_product_variant_list for one parent\'s variants. Results are paginated via "page" and "limit". Every product or variant in the result carries "resourceKey" ("products"). Pass it with the "uuid" and the locale to the admin link tool to build a link.',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW),
    ])]
    public function listProducts(
        string $locale,
        #[Schema(description: 'Filter by product type. One of "product", "product_with_variants" or "variant". Omit to list every non-variant product.', enum: ['product', 'product_with_variants', 'variant'])]
        ?string $type = null,
        #[Schema(description: 'Include variants in the result. Ignored when "type" is set explicitly.')]
        bool $includeVariants = false,
        int $page = 1,
        int $limit = 20,
        #[Schema(description: 'Field to sort by. Defaults to "title".', enum: ['title', 'created', 'changed'])]
        string $sortBy = 'title',
        #[Schema(description: 'Sort direction, "asc" or "desc". Defaults to "asc".', enum: ['asc', 'desc'])]
        string $sortOrder = 'asc',
    ): array {
        if (!\in_array($sortBy, self::ALLOWED_SORT_FIELDS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported sortBy "%s". Supported: %s.', $sortBy, \implode(', ', self::ALLOWED_SORT_FIELDS)));
        }

        if (!\in_array($sortOrder, self::ALLOWED_SORT_ORDERS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported sortOrder "%s". Supported: %s.', $sortOrder, \implode(', ', self::ALLOWED_SORT_ORDERS)));
        }

        try {
            $filters = [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
                'page' => $page,
                'limit' => $limit,
            ];

            if (null !== $type) {
                $filters['types'] = [$type];
            } elseif (!$includeVariants) {
                $filters['excludeTypes'] = [ProductInterface::TYPE_VARIANT];
            }

            $sortBys = [$sortBy => $sortOrder];
            $total = $this->productRepository->countBy($filters);

            // Two-step paging, see PageListTool: a limit on the admin select truncates joined rows.
            $uuids = [...$this->productRepository->findIdentifiersBy($filters, $sortBys)];
            if ([] === $uuids) {
                return ['products' => [], 'total' => $total, 'page' => $page, 'limit' => $limit];
            }

            $products = $this->productRepository->findBy(
                ['uuids' => $uuids, 'locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT],
                $sortBys,
                [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_ADMIN => true],
            );

            return [
                'products' => $this->summarize($products, $locale),
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to list products: %s', $e->getMessage()),
                'hint' => 'Verify the locale is configured for this installation.',
            ];
        }
    }

    /**
     * @param iterable<ProductInterface> $products
     *
     * @return list<array<string, mixed>>
     */
    private function summarize(iterable $products, string $locale): array
    {
        $results = [];

        foreach ($products as $product) {
            $dimensionContent = $this->contentManager->resolve($product, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);

            $normalized = $this->contentManager->normalize($dimensionContent);

            $summary = [];
            foreach (self::SUMMARY_FIELDS as $field) {
                if (\array_key_exists($field, $normalized)) {
                    $summary[$field] = $normalized[$field];
                }
            }

            $results[] = [
                'uuid' => $product->getUuid(),
                'resourceKey' => ProductInterface::RESOURCE_KEY,
                'type' => $product->getType(),
                'parent' => $product->getParent()?->getUuid(),
                'data' => $summary,
            ];
        }

        return $results;
    }
}
