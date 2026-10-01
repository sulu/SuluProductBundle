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
use Sulu\Mcp\Application\Content\ContentNormalizerTrait;
use Sulu\Mcp\Application\Content\ShadowTrait;
use Sulu\Mcp\Domain\Security\DangerousTool;
use Sulu\Mcp\Domain\Security\PermissionRequirement;
use Sulu\Mcp\Domain\Security\RequiresPermission;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
final class ProductUpdateTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;
    use ContentNormalizerTrait;
    use ShadowTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ContentManagerInterface $contentManager,
        private readonly ProductRepositoryInterface $productRepository,
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
        name: 'sulu_product_update',
        title: 'Update Product',
        description: 'Update an existing product. Reads the current state, merges your changes and writes back, so pass only what should change. "attributes" is a map keyed by the INTEGER attribute id (sulu_attribute_list) and is merged into the existing values — pass null for an id to clear it. Changing "productFamily" changes which attributes the product may carry. This tool does not change a product\'s type or parent: use sulu_product_variant_update for variants. The product stays a draft — call sulu_content_publish (resourceKey: products) to make changes live.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false),
    )]
    #[DangerousTool('product_write')]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT),
    ])]
    public function updateProduct(
        string $uuid,
        string $locale,
        ?string $title = null,
        #[Schema(description: 'Product code (SKU). Must stay unique across all products.')]
        ?string $code = null,
        ?string $status = null,
        #[Schema(description: 'UUID of a different product family. Changing it changes which attributes apply.')]
        ?string $productFamily = null,
        ?string $template = null,
        #[Schema(type: 'object', description: 'Template field values as a flat object, e.g. {"description": "<p>…</p>"}. Merged into the current content. May include a "blocks" tree; block _ids are assigned automatically.', additionalProperties: true)]
        ?array $content = null,
        #[Schema(type: 'object', description: 'Attribute values keyed by the INTEGER attribute id, e.g. {"12": "red"}. Merged into the existing values; pass null for an id to clear that attribute.', additionalProperties: true)]
        ?array $attributes = null,
        #[Schema(type: 'object', description: 'Detail fields, e.g. {"shortDescription": "<p>…</p>"}. Media fields take {"id": <mediaId>}.', additionalProperties: true)]
        ?array $details = null,
        #[Schema(type: 'object', description: 'Optional excerpt/teaser fields. Call sulu_get_context for the exact field list.', additionalProperties: true)]
        ?array $excerpt = null,
        #[Schema(type: 'object', description: 'Optional SEO fields. Call sulu_get_context for the exact field list.', additionalProperties: true)]
        ?array $seo = null,
        #[Schema(type: 'boolean', description: 'Optional "Shadow" setting: when true this locale serves the content of "shadowLocale" instead of its own. Omit to leave it unchanged, pass false to remove the shadow. Cannot be combined with a link.')]
        ?bool $shadowOn = null,
        #[Schema(type: 'string', description: 'The locale mirrored when shadowOn is true, e.g. "en". The eligible locales are returned as "shadowLocales" by the matching get tool.')]
        ?string $shadowLocale = null,
    ): array {
        try {
            $product = $this->productRepository->getOneBy(
                [
                    'uuid' => $uuid,
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
                [
                    ProductRepositoryInterface::GROUP_SELECT_PRODUCT_ADMIN => true,
                ],
            );

            if ($product->isType(ProductInterface::TYPE_VARIANT)) {
                return [
                    'error' => \sprintf('Product %s is a variant.', $uuid),
                    'hint' => 'Use sulu_product_variant_update, which keeps the family inherited from the parent and accepts only variant-specific attributes.',
                ];
            }

            $currentDimensionContent = $this->contentManager->resolve($product, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);
            $currentData = $this->contentManager->normalize($currentDimensionContent);

            $data = \array_merge($currentData, ['locale' => $locale]);

            if (null !== $title) {
                $data['title'] = $title;
            }
            if (null !== $code) {
                $data['code'] = $code;
            }
            if (null !== $status) {
                $data['status'] = $status;
            }
            if (null !== $productFamily) {
                $data['productFamily'] = $productFamily;
            }
            if (null !== $template) {
                $data['template'] = $template;
            }
            if (null !== $content) {
                $normalizedContent = self::normalizeContent($content);
                $templateKey = \is_string($data['template'] ?? null) ? $data['template'] : null;
                if ($validationError = $this->blockDataValidator->validateContentTree($normalizedContent, 'product', $templateKey)) {
                    return $validationError;
                }
                $data = \array_merge($data, $this->assignBlockIds($normalizedContent, $this->blockIdGenerator));
            }
            if (null !== $attributes) {
                $current = \is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
                $data['attributes'] = \array_replace($current, $attributes);
            }
            if (null !== $details) {
                $current = \is_array($data['details'] ?? null) ? $data['details'] : [];
                $data['details'] = \array_replace($current, $details);
            }

            $data = $this->contentMetadataMapper->applyExcerpt($data, $excerpt, $locale);
            if (isset($data['error'])) {
                return $data;
            }
            $data = $this->contentMetadataMapper->applySeo($data, $seo, $locale);
            if (isset($data['error'])) {
                return $data;
            }

            // Only the variant tools may set these.
            unset($data['type'], $data['parent']);

            if ($validationError = $this->validateShadow($shadowOn, $shadowLocale, $locale, $currentData)) {
                return $validationError;
            }

            $data = $this->applyShadow($data, $shadowOn, $shadowLocale);

            /** @var array{locale: string} $data */
            $data = $this->stringifyKeys($data);

            /** @var ProductInterface $updated */
            $updated = $this->handle(new Envelope(new ModifyProductMessage(['uuid' => $uuid], $data), [new EnableFlushStamp()]));

            $dimensionContent = $this->contentManager->resolve($updated, [
                'locale' => $locale,
                'stage' => DimensionContentInterface::STAGE_DRAFT,
            ]);
            $normalized = $this->contentManager->normalize($dimensionContent);

            $result = [
                'success' => true,
                'uuid' => $updated->getUuid(),
                'type' => $updated->getType(),
                'data' => $this->compactContent($normalized, $this->detectBlockProperties($normalized)),
            ];

            $adminUrl = $this->adminLinkGenerator->generate('product', [
                'locale' => $locale,
                'uuid' => $updated->getUuid(),
            ]);
            if (null !== $adminUrl) {
                $result['admin_url'] = $adminUrl;
            }

            return $result;
        } catch (ProductNotFoundException) {
            return [
                'error' => 'Product not found: ' . $uuid,
                'hint' => 'Verify the UUID and locale. Use sulu_product_list to find products.',
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to update product %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify "code" stays unique and that every attribute the family marks required is still set. Attribute keys are the integer ids from sulu_attribute_list.',
            ];
        }
    }
}
