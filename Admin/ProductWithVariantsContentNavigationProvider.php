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

class ProductWithVariantsContentNavigationProvider extends ProductContentNavigationProvider implements ContentNavigationProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getNavigationItems(array $options = [])
    {
        // Product with variants has all the navigation items a normal product has.
        $navigationItems = parent::getNavigationItems($options);

        $variants = new ContentNavigationItem('content-navigation.product.variants');
        $variants->setId('variants');
        $variants->setAction('variants');
        $variants->setPosition(19);
        $variants->setComponent('products/components/variants-list@suluproduct');
        $variants->setDisplay(['edit']);
        $variants->setResetStore(false);

        return array_merge(
            $navigationItems,
            [
                $variants,
            ]
        );
    }
}
