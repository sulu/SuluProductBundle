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

namespace Sulu\Product\Tests\Unit\Application\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Mcp\DefaultProductUrlResolver;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorGeneratorInterface;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorRequest;

#[CoversClass(DefaultProductUrlResolver::class)]
final class DefaultProductUrlResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ResourceLocatorGeneratorInterface> */
    private ObjectProphecy $generator;

    protected function setUp(): void
    {
        $this->generator = $this->prophesize(ResourceLocatorGeneratorInterface::class);
    }

    public function testResolveGeneratesTheUrlFromTheConfiguredRouteSchema(): void
    {
        $this->generator->generate(Argument::that(
            static fn (ResourceLocatorRequest $request): bool => ['title' => 'Monstera'] === $request->parts
                && 'en' === $request->locale
                && 'products' === $request->resourceKey
                && null === $request->resourceId
                && '/plants/{object.title}' === $request->routeSchema,
        ))->willReturn('/plants/monstera');

        $resolver = new DefaultProductUrlResolver(
            $this->generator->reveal(),
            'route',
            ['route_schema' => '/plants/{object.title}'],
        );

        $this->assertSame('/plants/monstera', $resolver->resolve('Monstera', 'en'));
    }

    public function testResolveReturnsNullWhenTheRouteFieldNeedsAParentPage(): void
    {
        $this->generator->generate(Argument::cetera())->shouldNotBeCalled();

        $resolver = new DefaultProductUrlResolver(
            $this->generator->reveal(),
            'page_tree_route',
            ['route_schema' => '/products/{implode(\'-\', object)}'],
        );

        $this->assertNull($resolver->resolve('Monstera', 'en'));
    }
}
