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

class ProductWithVariantsContentNavigationProvider implements ContentNavigationProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getNavigationItems(array $options = [])
    {
        $details = new ContentNavigationItem('content-navigation.product.general');
        $details->setId('details');
        $details->setAction('details');
        $details->setPosition(10);
        $details->setComponent('products/components/detail-form@suluproduct');
        $details->setResetStore(false);

        $variants = new ContentNavigationItem('content-navigation.product.variants');
        $variants->setId('variants');
        $variants->setAction('variants');
        $variants->setPosition(20);
        $variants->setComponent('products/components/variants-list@suluproduct');
        $variants->setDisplay(['edit']);
        $variants->setResetStore(false);

        $attributes = new ContentNavigationItem('content-navigation.product.attributes');
        $attributes->setId('attributes');
        $attributes->setAction('attributes');
        $attributes->setPosition(30);
        $attributes->setComponent('products/components/attributes@suluproduct');
        $attributes->setDisplay(['edit']);
        $attributes->setResetStore(false);

        return [
            $details,
            $variants,
            $attributes,
        ];
    }
}
