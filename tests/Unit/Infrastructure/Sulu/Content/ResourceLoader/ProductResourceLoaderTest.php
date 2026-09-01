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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;

class ProductResourceLoaderTest extends TestCase
{
    use ProphecyTrait;

    private const VARIANT_QUERY_PARAMETER = 'variant';

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    private ProductResourceLoader $loader;

    public function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->loader = new ProductResourceLoader(
            $this->productRepository->reveal(),
            self::VARIANT_QUERY_PARAMETER,
        );
    }

    public function testGetKey(): void
    {
        $this->assertSame('product', $this->loader::getKey());
    }

    public function testLoad(): void
    {
        $product1 = $this->createProduct('1');
        $product2 = $this->createProduct('3');

        $this->expectFindBy(['1', '3'], [$product1, $product2]);
        $this->productRepository->findSlugsBy(Argument::any())->shouldNotBeCalled();

        $result = $this->loader->load(['1', '3'], 'en');
        $this->assertSame(['1' => $product1, '3' => $product2], $result);
    }

    public function testLoadReturnsEmptyArrayWhenLocaleIsNull(): void
    {
        $this->productRepository->findBy()->shouldNotBeCalled();

        $result = $this->loader->load(['1', '2'], null);
        $this->assertSame([], $result);
    }

    public function testLoadQueriesEveryParentOfTheBatchOnce(): void
    {
        $parent = $this->createProduct('parent-1');
        $variant1 = $this->createVariant('variant-1', $parent);
        $variant2 = $this->createVariant('variant-2', $parent);

        $this->expectFindBy(['variant-1', 'variant-2'], [$variant1, $variant2]);
        $this->expectParentSlugQuery(['parent-1'], ['parent-1' => '/parent']);

        $result = $this->loader->load(['variant-1', 'variant-2'], 'en');
        $this->assertSame(['variant-1' => $variant1, 'variant-2' => $variant2], $result);
    }

    public function testResolveContentViewEnhancementAppendsTheVariantCodeToTheParentUrl(): void
    {
        $parent = $this->createProduct('parent-1');
        $variant = $this->createVariant('variant-1', $parent);

        $this->expectFindBy(['variant-1'], [$variant]);
        $this->expectParentSlugQuery(['parent-1'], ['parent-1' => '/parent']);
        $this->loader->load(['variant-1'], 'en');

        $contentView = $this->loader->resolveContentViewEnhancement(
            $this->createDimensionContent($variant, 'en', 'CODE-1'),
        );

        $this->assertSame(['url' => '/parent?variant=CODE-1'], $contentView->getContent());
        $this->assertSame([], $contentView->getView());
    }

    public function testResolveContentViewEnhancementOmitsTheCodeOfTheDefaultVariant(): void
    {
        $parent = $this->createProduct('parent-1');
        $variant = $this->createVariant('variant-1', $parent, 0);

        $this->expectFindBy(['variant-1'], [$variant]);
        $this->expectParentSlugQuery(['parent-1'], ['parent-1' => '/parent']);
        $this->loader->load(['variant-1'], 'en');

        $contentView = $this->loader->resolveContentViewEnhancement(
            $this->createDimensionContent($variant, 'en', 'CODE-1'),
        );

        // The bare parent URL shows position 0 already, so its code would be a second address.
        $this->assertSame(['url' => '/parent'], $contentView->getContent());
    }

    public function testResolveContentViewEnhancementIgnoresAForeignResource(): void
    {
        $contentView = $this->loader->resolveContentViewEnhancement(new \stdClass());

        $this->assertSame([], $contentView->getContent());
    }

    /**
     * @return \Generator<string, array{string, string|null, string|null, bool}>
     */
    public static function provideNonLinkableVariants(): \Generator
    {
        yield 'no variant type' => [ProductInterface::TYPE_PRODUCT, 'en', 'CODE-1', true];
        yield 'no parent' => [ProductInterface::TYPE_VARIANT, 'en', 'CODE-1', false];
        yield 'no code' => [ProductInterface::TYPE_VARIANT, 'en', null, true];
        yield 'empty code' => [ProductInterface::TYPE_VARIANT, 'en', '', true];
        yield 'no locale' => [ProductInterface::TYPE_VARIANT, null, 'CODE-1', true];
    }

    #[DataProvider('provideNonLinkableVariants')]
    public function testResolveContentViewEnhancementIgnoresAnythingButALinkableVariant(
        string $type,
        ?string $locale,
        ?string $code,
        bool $withParent,
    ): void {
        $product = $this->createProduct('product-1')->setType($type);

        if ($withParent) {
            $product->setParent($this->createProduct('parent-1'));
        }

        $contentView = $this->loader->resolveContentViewEnhancement(
            $this->createDimensionContent($product, $locale, $code),
        );

        $this->assertSame([], $contentView->getContent());
    }

    public function testResolveContentViewEnhancementIgnoresAnUnpublishedParent(): void
    {
        $parent = $this->createProduct('parent-1');
        $variant = $this->createVariant('variant-1', $parent);

        $this->expectFindBy(['variant-1'], [$variant]);
        $this->expectParentSlugQuery(['parent-1'], []);
        $this->loader->load(['variant-1'], 'en');

        $contentView = $this->loader->resolveContentViewEnhancement(
            $this->createDimensionContent($variant, 'en', 'CODE-1'),
        );

        $this->assertSame([], $contentView->getContent());
    }

    /**
     * A shadow page re-aggregates the child at the shadow locale, so the dimension content that
     * arrives here carries a different locale than the one load() ran with.
     */
    public function testResolveContentViewEnhancementResolvesContentInAShadowLocale(): void
    {
        $parent = $this->createProduct('parent-1');
        $variant = $this->createVariant('variant-1', $parent);

        $this->expectFindBy(['variant-1'], [$variant]);
        $this->expectParentSlugQuery(['parent-1'], ['parent-1' => '/parent']);
        $this->loader->load(['variant-1'], 'en');

        $contentView = $this->loader->resolveContentViewEnhancement(
            $this->createDimensionContent($variant, 'de', 'CODE-1'),
        );

        $this->assertSame(['url' => '/parent?variant=CODE-1'], $contentView->getContent());
    }

    /** The map holds one locale, so a load() in another starts it over rather than answering wrong. */
    public function testLoadDropsTheCollectedSlugsWhenTheLocaleChanges(): void
    {
        $parent = $this->createProduct('parent-1');
        $variant = $this->createVariant('variant-1', $parent);

        $this->expectFindBy(['variant-1'], [$variant]);
        $this->expectParentSlugQuery(['parent-1'], ['parent-1' => '/parent']);
        $this->loader->load(['variant-1'], 'en');

        $this->expectFindBy(['variant-1'], [$variant], 'de');
        $this->expectParentSlugQuery(['parent-1'], ['parent-1' => '/eltern'], 'de');
        $this->loader->load(['variant-1'], 'de');

        $contentView = $this->loader->resolveContentViewEnhancement(
            $this->createDimensionContent($variant, 'de', 'CODE-1'),
        );

        $this->assertSame(['url' => '/eltern?variant=CODE-1'], $contentView->getContent());
    }

    /** A parent already looked up is not asked for again. */
    public function testLoadDoesNotRequeryAParentItAlreadyKnows(): void
    {
        $parent = $this->createProduct('parent-1');
        $variant1 = $this->createVariant('variant-1', $parent);
        $variant2 = $this->createVariant('variant-2', $parent);

        $this->expectFindBy(['variant-1'], [$variant1]);
        $this->expectParentSlugQuery(['parent-1'], ['parent-1' => '/parent']);
        $this->loader->load(['variant-1'], 'en');

        $this->expectFindBy(['variant-2'], [$variant2]);
        $this->loader->load(['variant-2'], 'en');

        $contentView = $this->loader->resolveContentViewEnhancement(
            $this->createDimensionContent($variant2, 'en', 'CODE-2'),
        );

        $this->assertSame(['url' => '/parent?variant=CODE-2'], $contentView->getContent());
    }

    public function testResetDropsTheCollectedParentSlugs(): void
    {
        $parent = $this->createProduct('parent-1');
        $variant = $this->createVariant('variant-1', $parent);

        $this->expectFindBy(['variant-1'], [$variant]);
        $this->expectParentSlugQuery(['parent-1'], ['parent-1' => '/parent']);
        $this->loader->load(['variant-1'], 'en');

        $this->loader->reset();

        $contentView = $this->loader->resolveContentViewEnhancement(
            $this->createDimensionContent($variant, 'en', 'CODE-1'),
        );

        $this->assertSame([], $contentView->getContent());
    }

    /**
     * @param string[] $ids
     * @param ProductInterface[] $products
     */
    private function expectFindBy(array $ids, array $products, string $locale = 'en'): void
    {
        $this->productRepository->findBy(
            ['uuids' => $ids, 'locale' => $locale, 'stage' => DimensionContentInterface::STAGE_LIVE],
            [],
            [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true]
        )->willReturn($products)->shouldBeCalled();
    }

    /**
     * @param string[] $uuids
     * @param array<string, string|null> $slugs
     */
    private function expectParentSlugQuery(array $uuids, array $slugs, string $locale = 'en'): void
    {
        $this->productRepository->findSlugsBy([
            'uuids' => $uuids,
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn($slugs)->shouldBeCalledOnce();
    }

    private function createDimensionContent(
        ProductInterface $product,
        ?string $locale,
        ?string $code,
    ): ProductDimensionContent {
        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setLocale($locale);
        $dimensionContent->setCode($code);

        return $dimensionContent;
    }

    private static function createVariant(string $uuid, ProductInterface $parent, int $position = 1): Product
    {
        $variant = self::createProduct($uuid);
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);
        $variant->setPosition($position);

        return $variant;
    }

    private static function createProduct(string $uuid): Product
    {
        return new Product($uuid);
    }
}
