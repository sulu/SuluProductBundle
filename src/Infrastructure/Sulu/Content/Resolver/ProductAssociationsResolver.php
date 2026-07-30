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
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Content\Application\ContentResolver\Resolver\ResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\MetadataResolver\MetadataResolver;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductAssociationInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;

/**
 * Exposes the product's association targets to the frontend under `extension.associations.*`.
 *
 * Which properties resolve per type is controlled by the merged `product_associations`
 * form metadata - projects override it by declaring `associations/<type>` fields with params.
 *
 * @internal
 */
class ProductAssociationsResolver implements ResolverInterface
{
    private const FORM_KEY = 'product_associations';

    private const FIELD_PREFIX = 'associations/';

    public function __construct(
        private readonly MetadataProviderInterface $formMetadataProvider,
        private readonly MetadataResolver $metadataResolver,
    ) {
    }

    public function resolve(DimensionContentInterface $dimensionContent, ?array $properties = null): ?ContentView
    {
        if (!$dimensionContent instanceof ProductDimensionContentInterface) {
            return null;
        }

        // merged content always carries a locale
        /** @var string $locale */
        $locale = $dimensionContent->getLocale();

        /** @var FormMetadata $formMetadata */
        $formMetadata = $this->formMetadataProvider->getMetadata(self::FORM_KEY, $locale, []);

        $items = [];
        $data = [];
        foreach ($formMetadata->getFlatFieldMetadata() as $name => $field) {
            if (!\str_starts_with($name, self::FIELD_PREFIX)) {
                continue;
            }

            // re-keyed to the bare type: slash keys would make MetadataResolver nest and
            // unwrap the per-type ContentViews, dropping resolvables and references
            $type = \substr($name, \strlen(self::FIELD_PREFIX));
            $items[$type] = $field;
            $data[$type] = \array_map(
                static fn (ProductAssociationInterface $association): string => $association->getTarget()->getUuid(),
                $dimensionContent->getAssociationsByType($type),
            );
        }

        return ContentView::create($this->metadataResolver->resolveItems($items, $data, $locale), []);
    }
}
