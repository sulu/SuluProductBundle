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
use Sulu\Mcp\Application\Content\ContentNormalizerTrait;
use Sulu\Mcp\Domain\Security\DangerousTool;
use Sulu\Mcp\Domain\Security\PermissionRequirement;
use Sulu\Mcp\Domain\Security\RequiresPermission;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Product\Application\Mcp\ProjectLocales;
use Sulu\Product\Application\Mcp\VariantParentResolver;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Domain\Exception\InvalidVariantParentException;
use Sulu\Product\Domain\Exception\UnknownLocaleException;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
final class ProductVariantUpdateTool
{
    use HandleTrait;
    use BlockDataNormalizerTrait;
    use ContentNormalizerTrait;
    use LoadsProductLocaleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ContentManagerInterface $contentManager,
        private readonly ProductRepositoryInterface $productRepository,
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
        name: 'sulu_product_variant_update',
        title: 'Update Product Variant',
        description: 'Update a variant of a product. Both the parent UUID and the variant UUID are required, and the variant must actually belong to that parent. The family stays inherited from the parent and cannot be changed here. If the variant has no content in "locale" yet, this call creates that locale. Then pass the localized fields (title, details), because there is nothing to merge into. In "attributes" pass only variant axes (the attributes the family marks variantSpecific), keyed by their attribute UUID; they are merged into the existing values and shared attributes are dropped. The variant stays a draft: publish it individually with sulu_content_publish (resourceKey: products, the variant\'s uuid), which only works while its parent is published in that locale. The result carries "resourceKey" ("products"). Pass it with the "uuid" and the locale to the admin link tool to build a link.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false),
    )]
    #[DangerousTool('product_write')]
    #[RequiresPermission(requirements: [
        new PermissionRequirement(ProductAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT),
    ])]
    public function updateProductVariant(
        string $locale,
        #[Schema(description: 'UUID of the parent product the variant belongs to.')]
        string $parentUuid,
        #[Schema(description: 'UUID of the variant to update.')]
        string $uuid,
        ?string $title = null,
        #[Schema(description: 'Variant code (SKU). Must stay unique across all products.')]
        ?string $code = null,
        ?string $status = null,
        #[Schema(type: 'object', description: 'Variant axis values keyed by the attribute UUID, merged into the existing values. Only attributes the family marks variantSpecific are kept.', additionalProperties: true)]
        ?array $attributes = null,
        #[Schema(type: 'object', description: 'Detail fields, merged into the existing values.', additionalProperties: true)]
        ?array $details = null,
    ): array {
        try {
            $parent = $this->variantParentResolver->resolveParent($parentUuid);
            $family = $this->variantParentResolver->resolveFamily($parent);
            $this->variantParentResolver->assertVariantOwnedByParent($parentUuid, $uuid);
        } catch (InvalidVariantParentException $e) {
            return [
                'error' => $e->getMessage(),
                'hint' => $e->getHint(),
            ];
        }

        try {
            [$variant, $existingData] = $this->loadProductLocale($uuid, $locale);
            $currentData = $existingData ?? [];

            $data = \array_merge($currentData, [
                'locale' => $locale,
                'productFamily' => (string) $family->getUuid(),
            ]);

            if (null !== $title) {
                $data['title'] = $title;
            }
            if (null !== $code) {
                $data['code'] = $code;
            }
            if (null !== $status) {
                $data['status'] = $status;
            }
            if (null !== $details) {
                $current = \is_array($data['details'] ?? null) ? $data['details'] : [];
                $data['details'] = \array_replace($current, $details);
            }

            $currentAttributes = \is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
            $mergedAttributes = null !== $attributes
                ? \array_replace($currentAttributes, $attributes)
                : $currentAttributes;
            $data['attributes'] = $this->variantParentResolver->stripInheritedAttributes($family, $mergedAttributes);

            unset($data['type'], $data['parent']);

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
                'resourceKey' => ProductInterface::RESOURCE_KEY,
                'parent' => $parentUuid,
                'data' => $this->compactContent($normalized, $this->detectBlockProperties($normalized)),
            ];

            $adminUrl = $this->adminLinkGenerator->generate('product_variant', [
                'locale' => $locale,
                'uuid' => $parentUuid,
            ]);
            if (null !== $adminUrl) {
                $result['admin_url'] = $adminUrl;
            }

            return $result;
        } catch (UnknownLocaleException $e) {
            return [
                'error' => $e->getMessage(),
                'hint' => $e->getHint(),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Failed to update variant %s: %s', $uuid, $e->getMessage()),
                'hint' => 'Verify "code" stays unique and that every variant-specific attribute the family marks required is still set, keyed by its UUID.',
            ];
        }
    }
}
