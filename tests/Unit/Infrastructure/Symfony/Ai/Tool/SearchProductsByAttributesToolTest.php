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
use Sulu\Product\Application\Ai\AttributeFilter;
use Sulu\Product\Application\Ai\ProductUrlGenerator;
use Sulu\Product\Application\Ai\SearchProductsByAttributes;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Ai\Tool\SearchProductsByAttributesTool;
use Sulu\Product\Tests\Unit\Application\Ai\Fixtures\FakeRouteGenerator;
use Symfony\AI\Agent\Toolbox\ToolCallArgumentResolver;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Platform\Result\ToolCall;

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
            new ProductUrlGenerator(new FakeRouteGenerator()),
        ));

        $result = $tool('en', []);

        $this->assertSame('no_match', $result['status']);
    }

    public function testAgentCallDenormalizesFiltersIntoAttributeFilterObjects(): void
    {
        $tool = \iterator_to_array((new ReflectionToolFactory())->getTool(SearchProductsByAttributesTool::class))[0];

        $arguments = (new ToolCallArgumentResolver())->resolveArguments(
            $tool,
            new ToolCall('call-1', $tool->getName(), [
                'locale' => 'en',
                'filters' => [['key' => 'current', 'value' => '16']],
            ]),
        );

        $this->assertEquals([new AttributeFilter('current', '16')], $arguments['filters']);
    }

    public function testFiltersSchemaDescribesAnArrayOfKeyValueObjectsWithTheFullDescription(): void
    {
        $tool = \iterator_to_array((new ReflectionToolFactory())->getTool(SearchProductsByAttributesTool::class))[0];

        $parameters = $tool->getParameters();
        $this->assertNotNull($parameters);

        $this->assertJsonStringEqualsJsonString(
            (string) \json_encode([
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => ['key' => ['type' => 'string'], 'value' => ['type' => 'string']],
                    'required' => ['key', 'value'],
                    'additionalProperties' => false,
                ],
                'description' => 'one to five filters ANDed together, "key" is the exact attribute key from sulu_product_get_attributes and "value" a literal value, a substring for text or options, an exact number for a number attribute, never a comparison, range or wildcard like "16A or more"',
            ]),
            (string) \json_encode($parameters['properties']['filters']),
        );
    }
}
