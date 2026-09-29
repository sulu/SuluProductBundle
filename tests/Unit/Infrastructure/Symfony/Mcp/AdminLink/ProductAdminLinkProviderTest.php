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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Mcp\AdminLink;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Infrastructure\Symfony\Mcp\AdminLink\ProductAdminLinkProvider;
use Sulu\Product\Tests\Unit\Fixture\TestViewRegistry;

#[CoversClass(ProductAdminLinkProvider::class)]
final class ProductAdminLinkProviderTest extends TestCase
{
    private ProductAdminLinkProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ProductAdminLinkProvider(new TestViewRegistry());
    }

    public function testGetTypeReturnsProduct(): void
    {
        $this->assertSame('product', $this->provider->getType());
    }

    public function testBuildPathResolvesTheProductEditView(): void
    {
        $this->assertSame(
            '/en/products/product-uuid',
            $this->provider->buildPath(['locale' => 'en', 'uuid' => 'product-uuid']),
        );
    }

    public function testBuildPathReturnsNullWithoutRequiredContext(): void
    {
        $this->assertNull($this->provider->buildPath(['locale' => 'en']));
        $this->assertNull($this->provider->buildPath(['uuid' => 'product-uuid']));
    }
}
