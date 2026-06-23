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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\ResourceLoader;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;

class ProductResourceLoaderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    private ProductResourceLoader $loader;

    public function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->loader = new ProductResourceLoader($this->productRepository->reveal());
    }

    public function testGetKey(): void
    {
        $this->assertSame('product', $this->loader::getKey());
    }

    public function testLoad(): void
    {
        $product1 = $this->createProduct('1');
        $product2 = $this->createProduct('3');

        $this->productRepository->findBy(
            ['uuids' => ['1', '3'], 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_LIVE],
            [],
            [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true]
        )->willReturn([$product1, $product2])->shouldBeCalled();

        $result = $this->loader->load(['1', '3'], 'en');
        $this->assertSame(['1' => $product1, '3' => $product2], $result);
    }

    public function testLoadReturnsEmptyArrayWhenLocaleIsNull(): void
    {
        $this->productRepository->findBy()->shouldNotBeCalled();

        $result = $this->loader->load(['1', '2'], null);
        $this->assertSame([], $result);
    }

    private static function createProduct(string $uuid): Product
    {
        return new Product(new ProductFamily(), $uuid);
    }
}
