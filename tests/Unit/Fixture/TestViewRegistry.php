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

namespace Sulu\Product\Tests\Unit\Fixture;

use Sulu\Bundle\AdminBundle\Admin\View\View;
use Sulu\Bundle\AdminBundle\Admin\View\ViewRegistry;
use Sulu\Bundle\AdminBundle\Exception\ViewNotFoundException;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

final class TestViewRegistry extends ViewRegistry
{
    public function __construct()
    {
    }

    public function findViewByName(string $name): View
    {
        if (ProductAdmin::EDIT_TABS_VIEW === $name) {
            return new View($name, '/:locale/products/:id', 'form');
        }

        throw new ViewNotFoundException($name);
    }
}
