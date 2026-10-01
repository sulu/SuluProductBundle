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
use Sulu\Bundle\AdminBundle\Application\BlockIdGenerator\BlockIdGeneratorInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Mcp\Application\AdminLink\AdminLinkGeneratorInterface;
use Sulu\Mcp\Application\Content\BlockDataNormalizerTrait;
use Sulu\Mcp\Application\Content\BlockDataValidator;
use Sulu\Mcp\Application\Content\ContentMetadataMapper;
use Sulu\Mcp\Application\Content\ShadowTrait;
use Sulu\Mcp\Domain\Security\DangerousTool;
use Sulu\Mcp\Domain\Security\PermissionRequirement;
use Sulu\Mcp\Domain\Security\RequiresPermission;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class ProductCreateTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;
    use ShadowTrait;

    /**
     * "variant" is excluded: only ProductVariantController validates the parent's type.
     *
     * @var list<string>
     */
    private const CREATABLE_TYPES = [
        ProductInterface::TYPE_PRODUCT,
        ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
    ];

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ContentManagerInterface $contentManager,
        private readonly ContentMetadataMapper $contentMetadataMapper,
        private readonly BlockDataValidator $blockDataValidator,
        private readonly BlockIdGeneratorInterface $blockIdGenerator,
        private readonly AdminLinkGeneratorInterface $adminLinkGenerator,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param array<string, mixed>|null $content
     * @param array<int, mixed>|null $attributes
     * @param array<string, mixed>|null $details
     * @param array<string, mixed>|null $excerpt
     * @param array<string, mixed>|null $seo
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_product_create',
        title: 'Create Product',
        description: 'Create a new product (draft). Workflow: 1) Call sulu_product_family_list to pick a family — "productFamily" is its UUID and is mandatory, because the family decides which attributes the product has. 2) Pass attribute values in "attributes" as a map keyed by the INTEGER attribute id, e.g. attributes={"12": "red", "15": 42} — get those ids from sulu_attribute_list. Attributes the family marks required must be present or the save is rejected. Template fields go in "content" as a flat object — call sulu_get_context for the product templates. Set type="product_with_variants" when the product should hold variants; its variant-specific attributes then belong on the variants, not here. To create the variants themselves use sulu_product_variant_create — this tool cannot create them. The product is created as a draft: call sulu_content_publish (resourceKey: products) to make it live.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false),
    )]
    #[DangerousTool('product_write')]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT),
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::ADD),
    ])]
    public function createProduct(
        string $locale,
        #[Schema(description: 'UUID of the product family, from sulu_product_family_list. Decides which attributes this product can carry.')]
        string $productFamily,
        string $title,
        #[Schema(description: 'Product code (SKU). Must be unique across all products.')]
        ?string $code = null,
        #[Schema(description: 'Product status. Defaults to "available" when omitted.')]
        ?string $status = null,
        #[Schema(description: 'Product type. "product" for a standalone product, "product_with_variants" for one that holds variants. Variants are created with sulu_product_variant_create, not here.', enum: ['product', 'product_with_variants'])]
        ?string $type = null,
        #[Schema(description: 'Template key. Defaults to the bundle default ("product") when omitted.')]
        ?string $template = null,
        #[Schema(type: 'object', description: 'Template field values as a flat object, e.g. {"description": "<p>…</p>"}. Call sulu_get_context to see the product templates and their fields. May include a "blocks" tree; block _ids are assigned automatically.', additionalProperties: true)]
        ?array $content = null,
        #[Schema(type: 'object', description: 'Attribute values keyed by the INTEGER attribute id from sulu_attribute_list, e.g. {"12": "red", "15": 42}. Keys that are not numeric are ignored by Sulu.', additionalProperties: true)]
        ?array $attributes = null,
        #[Schema(type: 'object', description: 'Detail fields, e.g. {"shortDescription": "<p>…</p>", "image": {"id": 12}}. Media fields take {"id": <mediaId>}.', additionalProperties: true)]
        ?array $details = null,
        #[Schema(type: 'object', description: 'Optional excerpt/teaser fields keyed by the project\'s excerpt field names. Media fields take {"id": <mediaId>}. Call sulu_get_context for the exact field list.', additionalProperties: true)]
        ?array $excerpt = null,
        #[Schema(type: 'object', description: 'Optional SEO fields keyed by the project\'s SEO field names. Call sulu_get_context for the exact field list.', additionalProperties: true)]
        ?array $seo = null,
        #[Schema(type: 'boolean', description: 'Optional "Shadow" setting: when true this locale serves the content of "shadowLocale" instead of its own. Omit to leave it unchanged, pass false to remove the shadow. Cannot be combined with a link.')]
        ?bool $shadowOn = null,
        #[Schema(type: 'string', description: 'The locale mirrored when shadowOn is true, e.g. "en". The eligible locales are returned as "shadowLocales" by the matching get tool.')]
        ?string $shadowLocale = null,
    ): array {
        if (null !== $type && !\in_array($type, self::CREATABLE_TYPES, true)) {
            return [
                'error' => \sprintf('Unsupported product type "%s".', $type),
                'hint' => \sprintf(
                    'This tool creates %s. Variants are created with sulu_product_variant_create, which derives their family and parent and rejects a parent that cannot hold variants.',
                    \implode(' or ', self::CREATABLE_TYPES),
                ),
            ];
        }

        try {
            $normalizedContent = null !== $content ? self::normalizeContent($content) : [];

            if ($validationError = $this->blockDataValidator->validateContentTree($normalizedContent, 'product', $template)) {
                return $validationError;
            }

            $normalizedContent = $this->stringifyKeys($this->assignBlockIds($normalizedContent, $this->blockIdGenerator));

            $data = \array_merge($normalizedContent, [
                'locale' => $locale,
                'productFamily' => $productFamily,
                'title' => $title,
            ]);

            if (null !== $code) {
                $data['code'] = $code;
            }
            if (null !== $status) {
                $data['status'] = $status;
            }
            if (null !== $type) {
                $data['type'] = $type;
            }
            if (null !== $template) {
                $data['template'] = $template;
            }
            if (null !== $attributes) {
                $data['attributes'] = $attributes;
            }
            if (null !== $details) {
                $data['details'] = $details;
            }

            $data = $this->contentMetadataMapper->applyExcerpt($data, $excerpt, $locale);
            if (isset($data['error'])) {
                return $data;
            }
            $data = $this->contentMetadataMapper->applySeo($data, $seo, $locale);
            if (isset($data['error'])) {
                return $data;
            }

            if ($validationError = $this->validateShadow($shadowOn, $shadowLocale, $locale, [])) {
                return $validationError;
            }

            $data = $this->applyShadow($data, $shadowOn, $shadowLocale);

            /** @var array{locale: string, productFamily: string} $data */
            $data = $this->stringifyKeys($data);

            /** @var ProductInterface $product */
            $product = $this->handle(new Envelope(new CreateProductMessage($data), [new EnableFlushStamp()]));

            $dimensionContent = $this->contentManager->resolve($product, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);

            $result = [
                'success' => true,
                'uuid' => $product->getUuid(),
                'type' => $product->getType(),
                'data' => $this->contentManager->normalize($dimensionContent),
            ];

            $adminUrl = $this->adminLinkGenerator->generate('product', [
                'locale' => $locale,
                'uuid' => $product->getUuid(),
            ]);
            if (null !== $adminUrl) {
                $result['admin_url'] = $adminUrl;
            }

            return $result;
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to create product "%s": %s', $title, $e->getMessage()),
                'hint' => 'Verify the productFamily UUID exists (sulu_product_family_list), that "code" is unique, and that every attribute the family marks required is present in "attributes" keyed by its integer id (sulu_attribute_list).',
            ];
        }
    }
}
