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

use Sulu\Bundle\AdminBundle\Navigation\ContentNavigation;
use Sulu\Bundle\AdminBundle\Navigation\ContentNavigationItem;

class SuluProductContentNavigation extends ContentNavigation
{
    public function __construct()
    {
        parent::__construct();

        $details = new ContentNavigationItem('Details');
        $details->setAction('details');
        $details->setGroups(array('product'));
        $details->setComponent('products@suluproduct');
        $details->setComponentOptions(array('display' => 'form'));
        $this->addNavigationItem($details);

        $variants = new ContentNavigationItem('Variants');
        $variants->setAction('variants');
        $variants->setGroups(array('product'));
        $variants->setComponent('products@suluproduct');
        $variants->setComponentOptions(array('display' => 'variants'));
        $this->addNavigationItem($variants);
    }
} 
