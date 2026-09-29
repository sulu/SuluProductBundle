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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Ai\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Ai\GetRelatedProducts;
use Sulu\Product\Application\Ai\ProductUrlGenerator;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Ai\Tool\GetRelatedProductsTool;
use Sulu\Product\Tests\Unit\Application\Ai\Fixtures\FakeRouteGenerator;

/**
 * GetRelatedProducts (final, so Prophecy can't double it directly) is real here, built over a
 * mocked repository; GetRelatedProductsTest covers its actual behavior in depth, this just
 * proves the adapter threads its arguments through.
 */
#[CoversClass(GetRelatedProductsTool::class)]
class GetRelatedProductsToolTest extends TestCase
{
    use ProphecyTrait;

    public function testInvokeDelegatesToGetRelatedProducts(): void
    {
        $productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $productRepository->getOneBy([
            'code' => 'MISSING',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willThrow(new ProductNotFoundException(['code' => 'MISSING']));

        $tool = new GetRelatedProductsTool(new GetRelatedProducts(
            $productRepository->reveal(),
            new ProductAssociationTypeRegistry([]),
            new ProductUrlGenerator(new FakeRouteGenerator()),
        ));

        $this->expectException(\InvalidArgumentException::class);

        $tool('MISSING', 'en');
    }
}
