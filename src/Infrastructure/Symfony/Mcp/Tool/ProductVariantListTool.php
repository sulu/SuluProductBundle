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
final class ProductVariantListTool
{
    private const SUMMARY_FIELDS = [
        'title', 'code', 'status', 'productFamily', 'attributes',
        'locale', 'stage', 'published', 'publishedState', 'workflowPlace',
        'created', 'changed',
    ];

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ContentManagerInterface $contentManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_product_variant_list',
        title: 'List Product Variants',
        description: 'List the variants of one product. Pass the UUID of a product of type "product_with_variants". Each entry includes its "attributes" map keyed by the attribute UUID, which is where the variant axes (the attributes the family marks variantSpecific) carry their distinguishing values. Returns an empty list for a product that has no variants. Every variant in the result carries "resourceKey" ("products").',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW),
    ])]
    public function listProductVariants(
        string $locale,
        string $parentUuid,
        int $page = 1,
        int $limit = 20,
    ): array {
        try {
            $filters = [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
                'parent' => $parentUuid,
                'page' => $page,
                'limit' => $limit,
            ];

            $sortBys = ['title' => 'asc'];
            $total = $this->productRepository->countBy($filters);

            // Two-step paging, see PageListTool: a limit on the admin select truncates joined rows.
            $uuids = [...$this->productRepository->findIdentifiersBy($filters, $sortBys)];
            if ([] === $uuids) {
                return ['variants' => [], 'parent' => $parentUuid, 'total' => $total, 'page' => $page, 'limit' => $limit];
            }

            $variants = $this->productRepository->findBy(
                ['uuids' => $uuids, 'locale' => $locale, 'stage' => DimensionContentInterface::STAGE_DRAFT],
                $sortBys,
                [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_ADMIN => true],
            );

            $results = [];
            foreach ($variants as $variant) {
                $results[] = [
                    'uuid' => $variant->getUuid(),
                    'resourceKey' => ProductInterface::RESOURCE_KEY,
                    'data' => $this->summarize($variant, $locale),
                ];
            }

            return [
                'variants' => $results,
                'parent' => $parentUuid,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to list variants of product %s: %s', $parentUuid, $e->getMessage()),
                'hint' => 'Verify the parent UUID exists (use sulu_product_list) and the locale is configured.',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(ProductInterface $variant, string $locale): array
    {
        $dimensionContent = $this->contentManager->resolve($variant, [
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

        return $summary;
    }
}
