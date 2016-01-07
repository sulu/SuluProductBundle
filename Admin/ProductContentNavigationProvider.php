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
    public function getNavigationItems(array $options = array())
    {
        $details = new ContentNavigationItem('content-navigation.product.general');
        $details->setAction('details');
        $details->setComponent('products/components/detail-form@suluproduct');
        $details->setResetStore(false);

        $pricing = new ContentNavigationItem('content-navigation.product.pricing');
        $pricing->setAction('pricing');
        $pricing->setComponent('products/components/pricing@suluproduct');
        $pricing->setDisplay(array('edit'));
        $pricing->setResetStore(false);

        // media
        $media = new ContentNavigationItem('content-navigation.product.media');
        $media->setAction('media');
        $media->setComponent('products/components/media@suluproduct');
        $media->setDisplay(array('edit'));
        $media->setResetStore(false);

        // attributes
        $attributes = new ContentNavigationItem('content-navigation.product.attributes');
        $attributes->setAction('attributes');
        $attributes->setComponent('products/components/attributes@suluproduct');
        $attributes->setDisplay(array('edit'));
        $attributes->setResetStore(false);

        return array(
            $details,
            $pricing,
            $media,
            $attributes
        );
    }
}
