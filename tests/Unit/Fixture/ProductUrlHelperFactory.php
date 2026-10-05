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

use Sulu\Product\Application\Mcp\ProductUrlHelper;
use Sulu\Route\Application\ResourceLocator\PathCleanup\PathCleanup;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * @internal
 */
final class ProductUrlHelperFactory
{
    public static function create(string $routeType = 'page_tree_route'): ProductUrlHelper
    {
        return new ProductUrlHelper($routeType, new PathCleanup(new AsciiSlugger()));
    }
}
