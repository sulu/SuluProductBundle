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

namespace Sulu\Product\Infrastructure\Sulu\Content\Resolver;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Content\Application\ContentResolver\Resolver\ResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\MetadataResolver\MetadataResolver;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductAssociationInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductFamilyContentViewFactory;
use Sulu\Product\Infrastructure\Sulu\Content\ProductParentContentLoader;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;

/**
 * Assembles the root-level `product` namespace. A reference passes `$properties` and gets the
 * always-on set plus what it asked for under `product.`, so a listing builds no full payload.
 *
 * @internal
 *
 * @final
 */
class ProductResolver implements ResolverInterface
{
    private const ASSOCIATIONS_FORM_KEY = 'product_associations';

    private const ASSOCIATIONS_FIELD_PREFIX = 'associations/';

    /**
     * @param array<string, string> $variantProperties the properties each entry of `variants` carries
     */
    public function __construct(
        private readonly FormMetadataLoaderInterface $formMetadataLoader,
        private readonly MetadataProviderInterface $formMetadataProvider,
        private readonly MetadataResolver $metadataResolver,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductParentContentLoader $parentContentLoader,
        private readonly ReferenceStoreInterface $referenceStore,
        private readonly array $variantProperties = [],
    ) {
    }

    public function resolve(DimensionContentInterface $dimensionContent, ?array $properties = null): ?ContentView
    {
        if (!$dimensionContent instanceof ProductDimensionContentInterface) {
            return null;
        }

        if ([] === $properties) {
            return null;
        }

        // merged content always carries a locale
        /** @var string $locale */
        $locale = $dimensionContent->getLocale();

        $requested = null === $properties
            ? null
            : [...$this->filterProperties($this->getDefaultProperties()), ...$this->filterProperties($properties)];

        // A reference, such as a product selection, resolves a variant as itself.
        $parentContent = null === $requested ? $this->parentContentLoader->getLoaded($dimensionContent) : null;

        if (null === $parentContent) {
            return ContentView::create($this->resolveProduct($dimensionContent, $locale, $requested), []);
        }

        // Tagged, so publishing the parent clears the variant's cached page.
        $this->referenceStore->add($parentContent->getResource()->getUuid(), ProductInterface::RESOURCE_KEY);

        $content = $this->resolveProduct($parentContent, $locale, null);
        $content['currentVariant'] = ContentView::create($this->resolveProduct($dimensionContent, $locale, null), []);

        return ContentView::create($content, []);
    }

    /**
     * @param array<string, string>|null $requested
     *
     * @return array<string, ContentView>
     */
    private function resolveProduct(
        ProductDimensionContentInterface $dimensionContent,
        string $locale,
        ?array $requested,
    ): array {
        $content = $this->resolveDetails($dimensionContent, $locale, $requested);

        if ($this->isRequested($requested, 'attributes')) {
            $content[$this->outputKey($requested, 'attributes')] = ContentView::create(
                $this->getAttributesMapByKey($dimensionContent),
                [],
            );
        }

        if ($this->isRequested($requested, 'associations')) {
            $content[$this->outputKey($requested, 'associations')] = ContentView::create(
                $this->resolveAssociations($dimensionContent, $locale),
                [],
            );
        }

        $product = $dimensionContent->getResource();
        if ($product->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS) && $this->isRequested($requested, 'variants')) {
            $variants = $this->resolveVariants($product, $locale);

            if (null !== $variants) {
                $content[$this->outputKey($requested, 'variants')] = $variants;
            }
        }

        return $content;
    }

    /** The prefix a referencing block addresses this resolver by. */
    public static function getPrefix(): string
    {
        return 'product.';
    }

    public function getType(): string
    {
        return 'product';
    }

    public function getOutputPath(): string
    {
        return '[product]';
    }

    /**
     * Always resolved for a reference; everything else is opt-in. `image` and `shortDescription`
     * are template fields rather than fixed columns, so a project whose template drops them
     * resolves without them: resolveDetailsData() skips a requested field the form does not declare.
     *
     * TODO use the prepended `sulu_product.variants.properties` map here too
     *
     * @return array<string, string>
     */
    private function getDefaultProperties(): array
    {
        return [
            'code' => self::getPrefix() . 'code',
            'externalIdentifier' => self::getPrefix() . 'externalIdentifier',
            'status' => self::getPrefix() . 'status',
            'productFamily' => self::getPrefix() . 'productFamily',
            'position' => self::getPrefix() . 'position',
            'image' => self::getPrefix() . 'image',
            'shortDescription' => self::getPrefix() . 'shortDescription',
        ];
    }

    /**
     * Keeps what carries this resolver's prefix and strips it, leaving output key => own name.
     *
     * @param array<string, string> $properties
     *
     * @return array<string, string>
     */
    private function filterProperties(array $properties): array
    {
        $filtered = [];
        foreach ($properties as $key => $value) {
            if (\str_starts_with($value, self::getPrefix())) {
                $filtered[$key] = \substr($value, \strlen(self::getPrefix()));
            }
        }

        return $filtered;
    }

    /** @param array<string, string>|null $requested */
    private function isRequested(?array $requested, string $name): bool
    {
        return null === $requested || \in_array($name, $requested, true);
    }

    /** @param array<string, string>|null $requested */
    private function outputKey(?array $requested, string $name): string
    {
        if (null === $requested) {
            return $name;
        }

        $key = \array_search($name, $requested, true);

        return \is_string($key) ? $key : $name;
    }

    /**
     * details/<field> cannot collide with the reserved names below:
     * ProductDetailsFieldMetadataValidator rejects them at cache warmup. The merge order here only
     * matters for non-reserved field names.
     *
     * @param array<string, string>|null $requested
     *
     * @return array<string, ContentView>
     */
    private function resolveDetails(
        ProductDimensionContentInterface $dimensionContent,
        string $locale,
        ?array $requested,
    ): array {
        $masterData = [
            'title' => ContentView::create($dimensionContent->getTitle(), []),
            'url' => ContentView::create($dimensionContent->getRoute()?->getSlug(), []),
            'code' => ContentView::create($dimensionContent->getCode(), []),
            'externalIdentifier' => ContentView::create($dimensionContent->getExternalIdentifier(), []),
            'productFamily' => $this->resolveProductFamily($dimensionContent, $locale),
            'status' => ContentView::create($dimensionContent->getStatus(), []),
            'position' => ContentView::create($dimensionContent->getResource()->getPosition(), []),
        ];

        // A product with variants owns no route, it is reached through its variants.
        if ($dimensionContent->getResource()->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)) {
            unset($masterData['url']);
        }

        if (null !== $requested) {
            $selected = [];
            foreach ($requested as $key => $name) {
                if (isset($masterData[$name])) {
                    $selected[$key] = $masterData[$name];
                }
            }
            $masterData = $selected;
        }

        return \array_merge($masterData, $this->resolveDetailsData($dimensionContent, $locale, $requested));
    }

    /** The family is already on the dimension content, so it is read here rather than re-loaded. */
    private function resolveProductFamily(
        ProductDimensionContentInterface $dimensionContent,
        string $locale,
    ): ContentView {
        $family = $dimensionContent->getProductFamily();

        if (null === $family) {
            return ContentView::create(null, []);
        }

        return ProductFamilyContentViewFactory::create($family, $locale);
    }

    /**
     * Resolves each `details/<field>` through the property resolver its XML `type` selects, so the
     * stored admin wire-shape (e.g. `{"id": …}`) reaches the matching resolver unchanged.
     *
     * @param array<string, string>|null $requested
     *
     * @return array<string, ContentView>
     */
    private function resolveDetailsData(
        ProductDimensionContentInterface $dimensionContent,
        string $locale,
        ?array $requested,
    ): array {
        $formMetadata = $this->formMetadataLoader->getMetadata(ProductInterface::FORM_KEY, $locale, []);

        if (!$formMetadata instanceof FormMetadata) {
            return [];
        }

        $items = [];
        foreach ($formMetadata->getFlatFieldMetadata() as $property) {
            $parts = \explode('/', $property->getName(), 2);
            if ('details' !== $parts[0] || !isset($parts[1])) {
                continue;
            }

            // re-keyed to the bare field name — `resolveItems()` looks the value up by array key
            $items[$parts[1]] = $property;
        }

        $data = $dimensionContent->getDetailsData();

        // Filtered before resolving: an unrequested field must not reach its property resolver.
        if (null !== $requested) {
            $selectedItems = [];
            $selectedData = [];
            foreach ($requested as $key => $name) {
                if (!isset($items[$name])) {
                    continue;
                }

                $selectedItems[$key] = $items[$name];
                if (\array_key_exists($name, $data)) {
                    $selectedData[$key] = $data[$name];
                }
            }

            $items = $selectedItems;
            $data = $selectedData;
        }

        return $this->metadataResolver->resolveItems($items, $data, $locale);
    }

    /**
     * Formatting and grouping are the `sulu_product_attribute_groups` Twig filter's job.
     *
     * @return array<string, ProductAttributeValueInterface>
     */
    private function getAttributesMapByKey(ProductDimensionContentInterface $dimensionContent): array
    {
        $attributes = [];

        foreach ($dimensionContent->getAttributes() as $value) {
            $attributes[$value->getAttribute()->getKey()] = $value;
        }

        return $attributes;
    }

    /**
     * The association targets, shaped by the `product_associations` form metadata a project may
     * override with its own `associations/<type>` fields.
     *
     * @return array<string, mixed>
     */
    private function resolveAssociations(ProductDimensionContentInterface $dimensionContent, string $locale): array
    {
        /** @var FormMetadata $formMetadata */
        $formMetadata = $this->formMetadataProvider->getMetadata(self::ASSOCIATIONS_FORM_KEY, $locale, []);

        $items = [];
        $data = [];
        foreach ($formMetadata->getFlatFieldMetadata() as $name => $field) {
            if (!\str_starts_with($name, self::ASSOCIATIONS_FIELD_PREFIX)) {
                continue;
            }

            // re-keyed to the bare type — resolveItems() keys its output by array key
            $type = \substr($name, \strlen(self::ASSOCIATIONS_FIELD_PREFIX));
            $items[$type] = $field;
            $data[$type] = \array_map(
                static fn (ProductAssociationInterface $association): string => $association->getTarget()->getUuid(),
                $dimensionContent->getAssociationsByType($type),
            );
        }

        return $this->metadataResolver->resolveItems($items, $data, $locale);
    }

    /**
     * The published variants as references carrying the configured properties. Below the flat
     * `[product]` path core replaces each by its fields, so an entry is `variant.title`.
     */
    private function resolveVariants(ProductInterface $product, string $locale): ?ContentView
    {
        $uuids = [...$this->productRepository->findIdentifiersBy(
            ['parent' => $product->getUuid(), 'locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
            // by position, `created` and `uuid` break ties
            ['position' => 'asc', 'created' => 'asc', 'uuid' => 'asc'],
        )];

        if ([] === $uuids) {
            return null;
        }

        return ContentView::createResolvablesWithReferences(
            ids: $uuids,
            resourceLoaderKey: ProductResourceLoader::getKey(),
            resourceKey: ProductInterface::RESOURCE_KEY,
            view: [],
            metadata: ['properties' => $this->variantProperties],
        );
    }
}
