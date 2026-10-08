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

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Application\Ai\AttributeFilter;
use Sulu\Product\Application\Ai\GetProducts;
use Sulu\Product\Application\Ai\ProductUrlGenerator;
use Sulu\Product\Application\Ai\SearchProductsByAttributes;
use Sulu\Product\Application\Routing\VariantRouting;
use Sulu\Product\Application\Routing\VariantSlugResolver;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Tests\Unit\Application\Ai\Fixtures\FakeRouteGenerator;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;

/**
 * Every sulu_* tool name mentioned in a description must be a registered tool.
 */
#[CoversNothing]
class McpToolReferencesTest extends TestCase
{
    use ProphecyTrait;

    private const NAME_PATTERN = '/\bsulu_[a-z]+(?:_[a-z]+)+\b/';

    public function testDescriptionsMentionOnlyRegisteredTools(): void
    {
        $registered = [];
        $texts = [];

        foreach ((array) \glob(__DIR__ . '/../../../../../../src/Infrastructure/Symfony/Mcp/Tool/*Tool.php') as $file) {
            /** @var class-string $class */
            $class = 'Sulu\\Product\\Infrastructure\\Symfony\\Mcp\\Tool\\' . \basename((string) $file, '.php');

            foreach ((new \ReflectionClass($class))->getMethods() as $method) {
                foreach ($method->getAttributes(McpTool::class) as $attribute) {
                    $tool = $attribute->newInstance();
                    $name = (string) $tool->name;
                    $registered[] = $name;
                    $texts[$name] = [(string) $tool->description];

                    foreach ($method->getParameters() as $parameter) {
                        foreach ($parameter->getAttributes(Schema::class) as $schema) {
                            $texts[$tool->name][] = (string) ($schema->newInstance()->description ?? '');
                        }
                    }
                }
            }
        }

        $this->assertContains('sulu_attribute_list', $registered);

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../../../../../vendor/sulu/mcp-bundle/src/UserInterface/Mcp/Tool', \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);

            if (!\str_ends_with($file->getFilename(), 'Tool.php')) {
                continue;
            }

            \preg_match("/name: '(sulu_[a-z_]+)'/", (string) \file_get_contents($file->getPathname()), $found);

            if ([] !== $found) {
                $registered[] = $found[1];
            }
        }

        $this->assertContains('sulu_get_context', $registered);

        foreach ($texts as $tool => $parts) {
            $this->assertUnknownToolsAbsent($registered, \implode("\n", $parts), (string) $tool);
        }
    }

    public function testInstructionsMentionOnlyRegisteredTools(): void
    {
        $registered = ['sulu_attribute_list', 'sulu_product_search_products_by_attributes', 'sulu_product_get_products'];

        $productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $productRepository->findBy(Argument::cetera())->willReturn([]);
        $attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $attributeRepository->findOneBy(Argument::cetera())->willReturn(null);
        $urlGenerator = new ProductUrlGenerator(new FakeRouteGenerator(), new VariantSlugResolver($this->createStub(RouteRepositoryInterface::class), VariantRouting::Route));

        $getProducts = new GetProducts($productRepository->reveal(), $urlGenerator);
        $search = new SearchProductsByAttributes($productRepository->reveal(), $attributeRepository->reveal(), $urlGenerator);

        $responses = [
            $getProducts('en'),
            $getProducts('en', 'nothing'),
            $search('en', []),
            $search('en', [new AttributeFilter('missing', 'red')]),
        ];

        $attributeRepository->findOneBy(Argument::cetera())->willReturn($this->prophesize(\Sulu\Product\Domain\Model\AttributeInterface::class)->reveal());
        $responses[] = $search('en', [new AttributeFilter('known', 'red')]);
        $responses[] = $search('en', [new AttributeFilter('known', 'red')], true);

        foreach ($responses as $response) {
            $this->assertIsString($response['instruction']);
            $this->assertUnknownToolsAbsent($registered, $response['instruction'], 'instruction');
        }
    }

    /**
     * @param list<string> $registered
     */
    private function assertUnknownToolsAbsent(array $registered, string $text, string $source): void
    {
        \preg_match_all(self::NAME_PATTERN, $text, $matches);

        foreach ($matches[0] as $name) {
            $this->assertContains($name, $registered, \sprintf('%s mentions the unregistered tool "%s".', $source, $name));
        }
    }
}
