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
use Sulu\Mcp\Application\AdminLink\AdminLinkGeneratorInterface;
use Sulu\Mcp\Application\Content\BlockDataNormalizerTrait;
use Sulu\Mcp\Domain\Security\DangerousTool;
use Sulu\Mcp\Domain\Security\PermissionRequirement;
use Sulu\Mcp\Domain\Security\RequiresPermission;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Product\Application\Mcp\ProjectLocales;
use Sulu\Product\Application\Mcp\VariantParentResolver;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Domain\Exception\InvalidVariantParentException;
use Sulu\Product\Domain\Exception\UnknownLocaleException;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
final class ProductVariantCreateTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ContentManagerInterface $contentManager,
        private readonly VariantParentResolver $variantParentResolver,
        private readonly AdminLinkGeneratorInterface $adminLinkGenerator,
        private readonly ProjectLocales $projectLocales,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param array<string, mixed>|null $attributes
     * @param array<string, mixed>|null $details
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_product_variant_create',
        title: 'Create Product Variant',
        description: 'Create a variant of an existing product (draft). The parent must be a product of type "product_with_variants". A plain product or another variant is rejected, because variants cannot be nested. The variant inherits its parent\'s product family, so there is no productFamily parameter. In "attributes" pass only the variant axes: the attributes the family marks variantSpecific (see sulu_product_family_list), keyed by their attribute UUID. Shared attributes belong on the parent and are dropped here; a variant-specific attribute the family marks required must be present. Variants are published individually with sulu_content_publish (resourceKey: products, the variant\'s uuid), and only after the parent is published in that locale; publishing the parent leaves its variants unpublished, and unpublishing the parent unpublishes its variants. The result carries "resourceKey" ("products").',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false),
    )]
    #[DangerousTool('product_write')]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT),
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::ADD),
    ])]
    public function createProductVariant(
        string $locale,
        #[Schema(description: 'UUID of the parent product. Must be of type "product_with_variants".')]
        string $parentUuid,
        string $title,
        #[Schema(description: 'Variant code (SKU). Must be unique across all products.')]
        ?string $code = null,
        ?string $status = null,
        #[Schema(type: 'object', description: 'Variant axis values keyed by the attribute UUID, e.g. {"<attribute uuid>": "red"}. Only attributes the family marks variantSpecific are kept; the rest are inherited from the parent and silently dropped.', additionalProperties: true)]
        ?array $attributes = null,
        #[Schema(type: 'object', description: 'Detail fields, e.g. {"shortDescription": "<p>…</p>"}. Media fields take {"id": <mediaId>}.', additionalProperties: true)]
        ?array $details = null,
    ): array {
        try {
            $this->projectLocales->assertExists($locale);
        } catch (UnknownLocaleException $e) {
            return [
                'error' => $e->getMessage(),
                'hint' => $e->getHint(),
            ];
        }

        try {
            $parent = $this->variantParentResolver->resolveParent($parentUuid);
            $family = $this->variantParentResolver->resolveFamily($parent);
        } catch (InvalidVariantParentException $e) {
            return [
                'error' => $e->getMessage(),
                'hint' => $e->getHint(),
            ];
        }

        try {
            $data = [
                'locale' => $locale,
                'title' => $title,
                'type' => ProductInterface::TYPE_VARIANT,
                'parent' => $parentUuid,
                'productFamily' => (string) $family->getUuid(),
            ];

            if (null !== $code) {
                $data['code'] = $code;
            }
            if (null !== $status) {
                $data['status'] = $status;
            }
            if (null !== $details) {
                $data['details'] = $details;
            }
            if (null !== $attributes) {
                $data['attributes'] = $this->variantParentResolver->stripInheritedAttributes($family, $attributes);
            }

            /** @var array{locale: string, productFamily: string} $data */
            $data = $this->stringifyKeys($data);

            /** @var ProductInterface $variant */
            $variant = $this->handle(new Envelope(new CreateProductMessage($data), [new EnableFlushStamp()]));

            $dimensionContent = $this->contentManager->resolve($variant, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);

            $result = [
                'success' => true,
                'uuid' => $variant->getUuid(),
                'resourceKey' => ProductInterface::RESOURCE_KEY,
                'parent' => $parentUuid,
                'data' => $this->contentManager->normalize($dimensionContent),
            ];

            $adminUrl = $this->adminLinkGenerator->generate('product_variant', [
                'locale' => $locale,
                'uuid' => $parentUuid,
            ]);
            if (null !== $adminUrl) {
                $result['admin_url'] = $adminUrl;
            }

            return $result;
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to create variant "%s" of product %s: %s', $title, $parentUuid, $e->getMessage()),
                'hint' => 'Verify "code" is unique and that every variant-specific attribute the family marks required is present in "attributes", keyed by its UUID (sulu_product_family_list shows which attributes are variantSpecific and required).',
            ];
        }
    }
}
