<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Admin;

use Sulu\Bundle\AdminBundle\Navigation\ContentNavigationItem;
use Sulu\Bundle\AdminBundle\Navigation\ContentNavigationProviderInterface;

class ProductContentNavigationProvider implements ContentNavigationProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getNavigationItems(array $options = [])
    {
        // Details
        $details = new ContentNavigationItem('content-navigation.product.general');
        $details->setId('details');
        $details->setAction('details');
        $details->setPosition(10);
        $details->setComponent('products/components/detail-form@suluproduct');
        $details->setResetStore(false);

        // Pricing
        $pricing = new ContentNavigationItem('content-navigation.product.pricing');
        $pricing->setId('pricing');
        $pricing->setAction('pricing');
        $pricing->setPosition(20);
        $pricing->setComponent('products/components/pricing@suluproduct');
        $pricing->setDisplay(['edit']);
        $pricing->setResetStore(false);

        // Media
        $media = new ContentNavigationItem('content-navigation.product.media');
        $media->setId('media');
        $media->setAction('media');
        $media->setPosition(30);
        $media->setComponent('products/components/media@suluproduct');
        $media->setDisplay(['edit']);
        $media->setResetStore(false);

        // Attributes
        $attributes = new ContentNavigationItem('content-navigation.product.attributes');
        $attributes->setId('attributes');
        $attributes->setAction('attributes');
        $attributes->setPosition(40);
        $attributes->setComponent('products/components/attributes@suluproduct');
        $attributes->setDisplay(['edit']);
        $attributes->setResetStore(false);

        // Addons
        $addons = new ContentNavigationItem('content-navigation.product.addons');
        $addons->setId('addons');
        $addons->setAction('addons');
        $addons->setPosition(50);
        $addons->setComponent('products/components/addons@suluproduct');
        $addons->setDisplay(['edit']);
        $addons->setResetStore(false);

        return [
            $details,
            $pricing,
            $media,
            $attributes,
            $addons,
        ];
    }
}
