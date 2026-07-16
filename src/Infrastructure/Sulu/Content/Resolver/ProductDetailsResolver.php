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
use Sulu\Content\Application\ContentResolver\Resolver\ResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\MetadataResolver\MetadataResolver;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Exposes the product Details master-data fields to the frontend under `extension.product.*`.
 *
 * @internal
 */
class ProductDetailsResolver implements ResolverInterface
{
    public function __construct(
        private readonly FormMetadataLoaderInterface $formMetadataLoader,
        private readonly MetadataResolver $metadataResolver,
    ) {
    }

    public function resolve(DimensionContentInterface $dimensionContent, ?array $properties = null): ?ContentView
    {
        if (!$dimensionContent instanceof ProductDimensionContentInterface) {
            return null;
        }

        return ContentView::create(
            [
                'code' => ContentView::create($dimensionContent->getCode(), []),
                'externalIdentifier' => ContentView::create($dimensionContent->getExternalIdentifier(), []),
                'productFamily' => ContentView::create($dimensionContent->getProductFamily()?->getUuid(), []),
                'status' => ContentView::create($dimensionContent->getStatus(), []),
            ] + $this->resolveDetailsData($dimensionContent),
            [],
        );
    }

    /**
     * Resolves every `details/<field>` through the property resolver selected by its XML `type`,
     * exactly as `TemplateResolver` resolves template data. The bucket stores the admin wire-shape
     * verbatim, so e.g. `single_media_selection` reaches `SingleMediaSelectionPropertyResolver`
     * as the `{"id": …}` it expects, and a project's own `details/*` field resolves with no
     * change to this class.
     *
     * @return array<string, ContentView>
     */
    private function resolveDetailsData(ProductDimensionContentInterface $dimensionContent): array
    {
        // only merged content is resolved, and it always carries the localized row's locale
        $locale = $dimensionContent->getLocale() ?? '';

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

        return $this->metadataResolver->resolveItems($items, $dimensionContent->getDetailsData(), $locale);
    }
}
