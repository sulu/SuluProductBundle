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
use PHPUnit\Framework\TestCase;
use Sulu\Mcp\Domain\Security\DangerousTool;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductCreateTool;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductUpdateTool;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductVariantCreateTool;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductVariantUpdateTool;

/**
 * Every write tool must carry #[DangerousTool('product_write')], so operators can disable writes.
 */
final class DangerousToolGatingTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function writeToolProvider(): iterable
    {
        yield 'create' => [ProductCreateTool::class, 'createProduct'];
        yield 'update' => [ProductUpdateTool::class, 'updateProduct'];
        yield 'variant create' => [ProductVariantCreateTool::class, 'createProductVariant'];
        yield 'variant update' => [ProductVariantUpdateTool::class, 'updateProductVariant'];
    }

    /**
     * @param class-string $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('writeToolProvider')]
    public function testGatedUnderProductWrite(string $class, string $method): void
    {
        $reflection = new \ReflectionMethod($class, $method);

        $mcpTool = $reflection->getAttributes(McpTool::class);
        self::assertNotEmpty($mcpTool, \sprintf('%s::%s() must declare #[McpTool].', $class, $method));

        $dangerousTool = $reflection->getAttributes(DangerousTool::class);
        self::assertCount(1, $dangerousTool, \sprintf('%s::%s() must declare exactly one #[DangerousTool].', $class, $method));
        self::assertSame('product_write', $dangerousTool[0]->newInstance()->category);
    }
}
