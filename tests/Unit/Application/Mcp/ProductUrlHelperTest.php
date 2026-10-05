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
use Sulu\Product\Application\Mcp\ProductUrlHelper;
use Sulu\Product\Tests\Unit\Fixture\ProductUrlHelperFactory;

#[CoversClass(ProductUrlHelper::class)]
final class ProductUrlHelperTest extends TestCase
{
    public function testAPageTreeRouteAsksForAPageAndASuffix(): void
    {
        $helper = ProductUrlHelperFactory::create('page_tree_route');

        $this->assertTrue($helper->isPageBased());
        $this->assertStringContainsString('"suffix"', $helper->instruction());
        $this->assertStringContainsString('"suffix"', $helper->warning());
    }

    public function testThePlainRouteTypeAsksForAString(): void
    {
        $helper = ProductUrlHelperFactory::create('route');

        $this->assertFalse($helper->isPageBased());
        $this->assertStringContainsString('path string', $helper->instruction());
        $this->assertStringNotContainsString('"page"', $helper->warning());
    }

    public function testTheSuffixMatchesTheSlugOfTheAdmin(): void
    {
        $data = ProductUrlHelperFactory::create()->completeUrlSuffix(
            ['title' => 'Hemd für Männer', 'url' => ['page' => ['uuid' => 'p', 'path' => '/products']]],
            'de',
        );

        $url = $data['url'] ?? null;
        $this->assertIsArray($url);
        $this->assertSame('/hemd-fuer-maenner', $url['suffix'] ?? null);
    }

    public function testAGivenSuffixStays(): void
    {
        $data = ['title' => 'Shirt', 'url' => ['page' => ['uuid' => 'p', 'path' => '/products'], 'suffix' => '/custom']];

        $this->assertSame($data, ProductUrlHelperFactory::create()->completeUrlSuffix($data, 'en'));
    }

    public function testAStringUrlIsNeverCompleted(): void
    {
        $data = ['title' => 'Shirt', 'url' => '/hat-red'];

        $this->assertSame($data, ProductUrlHelperFactory::create('route')->completeUrlSuffix($data, 'en'));
    }
}
