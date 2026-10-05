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

use Prophecy\Argument;
use Prophecy\Prophet;
use Sulu\Product\Application\Mcp\ProductUrlHelper;
use Sulu\Route\Application\ResourceLocator\PathCleanup\PathCleanup;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorGenerator;
use Sulu\Route\Application\ResourceLocator\RouteSchemaEvaluator;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
final class ProductUrlHelperFactory
{
    /**
     * @param list<string> $takenSlugs routes that exist already
     */
    public static function create(string $routeType = 'page_tree_route', array $takenSlugs = [], string $routeSchema = "/products/{implode('-', object)}"): ProductUrlHelper
    {
        $prophet = new Prophet();
        $pathCleanup = new PathCleanup(new AsciiSlugger(), []);

        $routeRepository = $prophet->prophesize(RouteRepositoryInterface::class);
        $routeRepository->existBy(Argument::that(
            static fn (array $criteria): bool => \in_array($criteria['slug'] ?? null, $takenSlugs, true),
        ))->willReturn(true);
        $routeRepository->existBy(Argument::any())->willReturn(false);

        $generator = new ResourceLocatorGenerator(
            $routeRepository->reveal(),
            new RouteSchemaEvaluator($prophet->prophesize(TranslatorInterface::class)->reveal(), $pathCleanup),
        );

        return new ProductUrlHelper(
            $routeType,
            ['route_schema' => $routeSchema],
            $pathCleanup,
            $generator,
        );
    }
}
