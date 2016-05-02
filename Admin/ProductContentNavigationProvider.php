<?php
/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Admin;

use Sulu\Bundle\AdminBundle\Navigation\ContentNavigationProviderInterface;
use Sulu\Bundle\AdminBundle\Navigation\ContentNavigationItem;

class ProductContentNavigationProvider implements ContentNavigationProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getNavigationItems(array $options = [])
    {
        $details = new ContentNavigationItem('content-navigation.product.general');
        $details->setAction('details');
        $details->setPosition(10);
        $details->setComponent('products/components/detail-form@suluproduct');
        $details->setResetStore(false);

        $pricing = new ContentNavigationItem('content-navigation.product.pricing');
        $pricing->setAction('pricing');
        $pricing->setPosition(20);
        $pricing->setComponent('products/components/pricing@suluproduct');
        $pricing->setDisplay(['edit']);
        $pricing->setResetStore(false);

        // media
        $media = new ContentNavigationItem('content-navigation.product.media');
        $media->setAction('media');
        $media->setPosition(30);
        $media->setComponent('products/components/media@suluproduct');
        $media->setDisplay(['edit']);
        $media->setResetStore(false);

        // attributes
        $attributes = new ContentNavigationItem('content-navigation.product.attributes');
        $attributes->setAction('attributes');
        $attributes->setPosition(40);
        $attributes->setComponent('products/components/attributes@suluproduct');
        $attributes->setDisplay(['edit']);
        $attributes->setResetStore(false);

        return [
            $details,
            $pricing,
            $media,
            $attributes
        ];
    }
}
