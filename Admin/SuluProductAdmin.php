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

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Navigation\Navigation;
use Sulu\Bundle\AdminBundle\Navigation\NavigationItem;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

class SuluProductAdmin extends Admin
{
    /**
     * @var SecurityCheckerInterface
     */
    private $securityChecker;

    public function __construct(SecurityCheckerInterface $securityChecker, $title)
    {
        $this->securityChecker = $securityChecker;
        $rootNavigationItem = new NavigationItem($title);
        $section = new NavigationItem('');

        $pim = new NavigationItem('navigation.pim');
        $pim->setIcon('asterisk');
        $pim->setPosition(10);

        if ($this->securityChecker->hasPermission('sulu.product.products', 'view')) {
            $products = new NavigationItem('navigation.pim.products', $pim);
            $products->setAction('pim/products');
            $products->setPosition(10);
        }

        if ($this->securityChecker->hasPermission('sulu.product.attributes', 'view')) {
            $attributes = new NavigationItem('navigation.pim.attributes', $pim);
            $attributes->setAction('pim/attributes');
            $attributes->setPosition(20);
        }

        if ($pim->hasChildren()) {
            $section->addChild($pim);
            $rootNavigationItem->addChild($section);
        }

        $this->setNavigation(new Navigation($rootNavigationItem));
    }

    /**
     * {@inheritdoc}
     */
    public function getCommands()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getJsBundleName()
    {
        return 'suluproduct';
    }

    public function getSecurityContexts()
    {
        return [
            'Sulu' => [
                'Product' => [
                    'sulu.product.attributes',
                    'sulu.product.products',
                ],
            ],
        ];
    }
}
