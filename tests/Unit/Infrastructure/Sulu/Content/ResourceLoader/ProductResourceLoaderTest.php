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
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Model\Product;
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
            $this->expectedSelects(true),
        )->willReturn([$product1, $product2])->shouldBeCalled();

        $result = $this->loader->load(['1', '3'], 'en');
        $this->assertSame(['1' => $product1, '3' => $product2], $result);
    }

    public function testLoadForAListWithoutAttributesLoadsOnlyTheAttributeValues(): void
    {
        $this->productRepository->findBy(
            ['uuids' => ['1'], 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_LIVE],
            [],
            $this->expectedSelects(['translations' => false]),
        )->willReturn([])->shouldBeCalled();

        $this->loader->load(['1'], 'en', ['properties' => ['title' => 'product.title', 'parentAttributes' => 'product.parent.attributes']]);
    }

    public function testLoadForAListWithAttributesLoadsTheirLabels(): void
    {
        $this->productRepository->findBy(
            ['uuids' => ['1'], 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_LIVE],
            [],
            $this->expectedSelects(true),
        )->willReturn([])->shouldBeCalled();

        $this->loader->load(['1'], 'en', ['properties' => ['specs' => 'product.attributes']]);
    }

    public function testLoadWithAudienceTargetingLoadsTheTargetGroups(): void
    {
        $loader = new ProductResourceLoader($this->productRepository->reveal(), true);

        $this->productRepository->findBy(
            ['uuids' => ['1'], 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_LIVE],
            [],
            [
                ...$this->expectedSelects(true),
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    DimensionContentQueryEnhancer::SELECT_EXCERPT_AUDIENCE_TARGET_GROUPS => true,
                ],
            ],
        )->willReturn([])->shouldBeCalled();

        $loader->load(['1'], 'en');
    }

    public function testLoadReturnsEmptyArrayWhenLocaleIsNull(): void
    {
        $this->productRepository->findBy()->shouldNotBeCalled();

        $result = $this->loader->load(['1', '2'], null);
        $this->assertSame([], $result);
    }

    /**
     * @param bool|array<string, bool> $attributes
     *
     * @return array<string, mixed>
     */
    private function expectedSelects(bool|array $attributes): array
    {
        return [
            ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true,
            ProductRepositoryInterface::SELECT_PRODUCT_ATTRIBUTES => $attributes,
            ProductRepositoryInterface::SELECT_PRODUCT_ASSOCIATIONS => true,
            ProductRepositoryInterface::SELECT_PRODUCT_ADDITIONAL_WEBSPACES => true,
            ProductRepositoryInterface::SELECT_PRODUCT_FAMILY_TRANSLATIONS => true,
        ];
    }

    private static function createProduct(string $uuid): Product
    {
        return new Product($uuid);
    }
}
