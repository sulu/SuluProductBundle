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

use Sulu\Bundle\AdminBundle\Admin\ContentNavigation;
use Sulu\Bundle\AdminBundle\Navigation\NavigationItem;

class SuluProductContentNavigation extends ContentNavigation
{
    public function __construct()
    {
        parent::__construct();

        $details = new NavigationItem('Details');
        $details->setAction('details');
        $details->setContentType('product');
        $details->setContentComponent('products@suluproduct');
        $details->setContentComponentOptions(array('display' => 'form'));
        $this->addNavigationItem($details);
    }
} 
