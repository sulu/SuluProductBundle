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
use Sulu\Mcp\Application\Content\ContentNormalizerTrait;
use Sulu\Mcp\Domain\Security\PermissionRequirement;
use Sulu\Mcp\Domain\Security\RequiresPermission;
use Sulu\Product\Application\Mcp\ProductCompletenessChecker;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

/**
 * @internal
 */
final class ProductGetTool
{
    use ContentNormalizerTrait;
    use LoadsProductLocaleTrait;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ContentManagerInterface $contentManager,
        private readonly ProductCompletenessChecker $completenessChecker,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_product_get',
        title: 'Get Product',
        description: 'Get a product by UUID, including its attribute values. Returns "productFamily" as the family UUID and "attributes" as a map keyed by the integer attribute id (e.g. {"12": "red"}). Resolve those ids to readable keys with sulu_attribute_list. Number attributes that carry a measurement unit also return a "<id>_unit" entry. "associations" maps each association type to the UUIDs of the linked products. "details" holds shortDescription, image and documents. "excerptCategories" and "excerptTags" are lists of integer ids. All of it can be passed back to sulu_product_update. A locale without content returns empty "data" and a hint. The result lists "recommendations" for things that are still empty. Work through them before publishing. Works for plain products, variant parents, and variants alike; use sulu_product_variant_list to see a parent\'s variants.',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW),
    ])]
    public function getProduct(string $locale, string $uuid): array
    {
        try {
            [$product, $normalized] = $this->loadProductLocale($uuid, $locale);

            if (null === $normalized) {
                return [
                    'uuid' => $product->getUuid(),
                    'locale' => $locale,
                    'type' => $product->getType(),
                    'parent' => $product->getParent()?->getUuid(),
                    'data' => [],
                    'hint' => \sprintf('The product has no content in "%s" yet. Call sulu_product_update (or sulu_product_variant_update for a variant) with this locale to create it.', $locale),
                ];
            }

            $result = [
                'uuid' => $product->getUuid(),
                'locale' => $locale,
                'type' => $product->getType(),
                'parent' => $product->getParent()?->getUuid(),
                'data' => $this->compactContent($normalized, $this->detectBlockProperties($normalized)),
            ];

            $recommendations = $this->completenessChecker->check($product, $normalized, $locale);
            if ([] !== $recommendations) {
                $result['recommendations'] = $recommendations;
            }

            return $result;
        } catch (ProductNotFoundException) {
            return [
                'error' => 'Product not found: ' . $uuid,
                'hint' => 'Verify the UUID. Use sulu_product_list to find products.',
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to get product %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify the UUID exists and the locale is configured for this installation.',
            ];
        }
    }
}
