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

namespace Sulu\Product\Infrastructure\Sulu\Content;

use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Infrastructure\Sulu\Content\ResourceLoader\MediaResourceLoader;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Product\Domain\Model\ProductFamilyInterface;

/**
 * Builds the website shape of a family, shared by a product's `productFamily` and the family selections.
 *
 * @internal
 */
final class ProductFamilyContentViewFactory
{
    public static function create(ProductFamilyInterface $family, string $locale): ContentView
    {
        $image = $family->getImage();

        return ContentView::create([
            'uuid' => $family->getUuid(),
            'externalIdentifier' => $family->getExternalIdentifier(),
            'name' => $family->getTranslation($locale)?->getName(),
            'image' => null === $image
                ? ContentView::create(null, [])
                // A resource key without a reference: the media tags the page, the family owns the reference.
                : ContentView::create(new ResolvableResource(
                    id: $image->getId(),
                    resourceLoaderKey: MediaResourceLoader::getKey(),
                    priority: 0,
                    resourceKey: MediaInterface::RESOURCE_KEY,
                ), []),
        ], []);
    }
}
