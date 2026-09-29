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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Application\Ai\SearchProductsByAttributes;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Ai\Tool\SearchProductsByAttributesTool;

/**
 * SearchProductsByAttributes (final, so Prophecy can't double it directly) is real here, built
 * over mocked repositories; SearchProductsByAttributesTest covers its actual search behavior in
 * depth, this just proves the adapter threads its arguments through.
 */
#[CoversClass(SearchProductsByAttributesTool::class)]
class SearchProductsByAttributesToolTest extends TestCase
{
    use ProphecyTrait;

    public function testInvokeDelegatesToSearchProductsByAttributes(): void
    {
        $productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $attributeRepository->findOneBy(Argument::cetera())->shouldNotBeCalled();

        $tool = new SearchProductsByAttributesTool(new SearchProductsByAttributes(
            $productRepository->reveal(),
            $attributeRepository->reveal(),
        ));

        $result = $tool('en', []);

        $this->assertSame('no_match', $result['status']);
    }
}
