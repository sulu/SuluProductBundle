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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Application\Ai\GetProducts;
use Sulu\Product\Application\Ai\ProductUrlGenerator;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\GetProductsTool;
use Sulu\Product\Tests\Unit\Application\Ai\Fixtures\FakeRouteGenerator;

/**
 * GetProducts (final, so Prophecy can't double it directly) is real here, built over a mocked
 * repository; GetProductsTest covers its actual search behavior in depth, this just proves the
 * adapter threads its arguments through.
 */
#[CoversClass(GetProductsTool::class)]
class GetProductsToolTest extends TestCase
{
    use ProphecyTrait;

    public function testSearchDelegatesToGetProducts(): void
    {
        $productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $productRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $tool = new GetProductsTool(new GetProducts($productRepository->reveal(), new ProductUrlGenerator(new FakeRouteGenerator())));

        $result = $tool->search('en');

        $this->assertSame('no_match', $result['status']);
    }
}
