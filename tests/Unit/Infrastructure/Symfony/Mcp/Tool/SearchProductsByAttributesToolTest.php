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
use Sulu\Product\Application\Ai\ProductUrlGenerator;
use Sulu\Product\Application\Ai\SearchProductsByAttributes;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\SearchProductsByAttributesTool;
use Sulu\Product\Tests\Unit\Application\Ai\Fixtures\FakeRouteGenerator;

#[CoversClass(SearchProductsByAttributesTool::class)]
class SearchProductsByAttributesToolTest extends TestCase
{
    use ProphecyTrait;

    public function testSearchDelegatesToSearchProductsByAttributes(): void
    {
        $productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $attributeRepository->findOneBy(Argument::cetera())->shouldNotBeCalled();

        $tool = new SearchProductsByAttributesTool(new SearchProductsByAttributes(
            $productRepository->reveal(),
            $attributeRepository->reveal(),
            new ProductUrlGenerator(new FakeRouteGenerator()),
        ));

        $result = $tool->search('en', []);

        $this->assertSame('no_match', $result['status']);
    }

    public function testSearchTurnsTheFilterArraysIntoAttributeFilters(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey('current');

        $productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $attributeRepository->findOneBy(['key' => 'current'])->willReturn($attribute);
        $productRepository->findBy(
            Argument::that(static fn (array $filters): bool => [['attribute' => $attribute, 'value' => '16']] === $filters['attributeValues']),
            Argument::any(),
        )->willReturn([])->shouldBeCalled();

        $tool = new SearchProductsByAttributesTool(new SearchProductsByAttributes(
            $productRepository->reveal(),
            $attributeRepository->reveal(),
            new ProductUrlGenerator(new FakeRouteGenerator()),
        ));

        $result = $tool->search('en', [['key' => 'current', 'value' => '16']]);

        $this->assertSame('no_match', $result['status']);
    }
}
