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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\Resolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStore;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ContentResolver\Value\Reference;
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;

#[CoversClass(ProductResolver::class)]
class ProductResolverVariantsTest extends ProductResolverTestCase
{
    public function testOmitsVariantsWhenResolvedAsAReference(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findIdentifiersBy');

        $content = $this->resolveContent(
            $this->createVariantParentContent(),
            ['title' => 'title'],
            $this->createResolver(productRepository: $productRepository),
        );

        self::assertArrayNotHasKey('variants', $content);
    }

    public function testOmitsVariantsForAProductThatHasNone(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findIdentifiersBy');

        $product = new ProductDimensionContent(new Product());
        $product->setLocale('de');
        $product->setStage('draft');

        $content = $this->resolveContent($product, null, $this->createResolver(productRepository: $productRepository));

        self::assertArrayNotHasKey('variants', $content);
    }

    public function testOmitsVariantsWhenTheRepositoryFindsNone(): void
    {
        $content = $this->resolveContent($this->createVariantParentContent());

        self::assertArrayNotHasKey('variants', $content);
    }

    /** Published variants only, whatever stage the page is on, in position order. */
    public function testResolvesThePublishedVariantsAsReferencesInPositionOrder(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())
            ->method('findIdentifiersBy')
            ->with(
                ['parent' => 'parent-uuid', 'locale' => 'de', 'stage' => DimensionContentInterface::STAGE_LIVE],
                ['position' => 'asc', 'created' => 'asc', 'uuid' => 'asc'],
            )
            ->willReturn(['variant-uuid-1', 'variant-uuid-2']);

        $variants = $this->resolveContent(
            $this->createVariantParentContent(),
            null,
            $this->createResolver(productRepository: $productRepository),
        )['variants'];

        self::assertInstanceOf(ContentView::class, $variants);
        self::assertSame(['variant-uuid-1', 'variant-uuid-2'], \array_map(
            static fn (ResolvableResource $resource): string|int => $resource->getId(),
            $this->resolvablesOf($variants),
        ));
        self::assertEquals(
            [new Reference('variant-uuid-1', ProductInterface::RESOURCE_KEY), new Reference('variant-uuid-2', ProductInterface::RESOURCE_KEY)],
            $variants->getReferences(),
        );
    }

    public function testVariantsCarryTheConfiguredProperties(): void
    {
        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findIdentifiersBy')->willReturn(['variant-uuid-1']);

        $variants = $this->resolveContent(
            $this->createVariantParentContent(),
            null,
            $this->createResolver(
                productRepository: $productRepository,
                variantProperties: ['code' => 'product.code', 'image' => 'product.image'],
            ),
        )['variants'];

        self::assertInstanceOf(ContentView::class, $variants);
        $resource = $this->resolvablesOf($variants)[0];
        self::assertSame(ProductResourceLoader::getKey(), $resource->getResourceLoaderKey());
        self::assertSame(['properties' => ['code' => 'product.code', 'image' => 'product.image']], $resource->getMetadata());
    }

    /** A variant URL renders its parent as `product` and adds the variant as `currentVariant`. */
    public function testAVariantUrlAddsTheVariantAsCurrentVariant(): void
    {
        $parentContent = $this->createVariantParentContent();
        $parentContent->setTitle('NC3FX');

        $content = $this->resolveContent(
            $parentContent,
            null,
            $this->createResolver(
                productRepository: $this->createVariantRepository(),
                currentVariant: $this->createVariantContent($parentContent->getResource()),
            ),
        );

        self::assertSame('NC3FX', $this->contentOf($content['title']));
        self::assertArrayHasKey('variants', $content, 'the parent lists every variant, the page\'s own included');

        $currentVariant = $this->contentOf($content['currentVariant']);
        self::assertIsArray($currentVariant);
        self::assertSame('NC3FX-B', $this->contentOf($currentVariant['title']));
        self::assertSame('NC3FX-B', $this->contentOf($currentVariant['code']));
        self::assertArrayNotHasKey('variants', $currentVariant);
        self::assertArrayNotHasKey('currentVariant', $currentVariant);
    }

    /** The core tags the page with its parent, publishing the variant must clear it too. */
    public function testAVariantPageIsTaggedWithItsVariant(): void
    {
        $parentContent = $this->createVariantParentContent();

        $referenceStore = new ReferenceStore();
        $this->resolveContent(
            $parentContent,
            null,
            $this->createResolver(
                referenceStore: $referenceStore,
                currentVariant: $this->createVariantContent($parentContent->getResource()),
            ),
        );

        self::assertSame(['products-variant-uuid-2'], \array_values($referenceStore->getAll()));
    }

    /** Resolved directly, as the reference index does, a variant carries only its own fields. */
    public function testAVariantResolvesAsItself(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findIdentifiersBy');

        $content = $this->resolveContent(
            $this->createVariantContent(new Product('parent-uuid')),
            null,
            $this->createResolver(productRepository: $productRepository),
        );

        self::assertSame('NC3FX-B', $this->contentOf($content['title']));
        self::assertArrayNotHasKey('currentVariant', $content);
        self::assertArrayNotHasKey('variants', $content);
    }

    public function testAReferenceToTheParentGetsNoCurrentVariant(): void
    {
        $parentContent = $this->createVariantParentContent();

        $referenceStore = new ReferenceStore();
        $content = $this->resolveContent(
            $parentContent,
            ['title' => 'product.title'],
            $this->createResolver(
                referenceStore: $referenceStore,
                currentVariant: $this->createVariantContent($parentContent->getResource()),
            ),
        );

        self::assertArrayNotHasKey('currentVariant', $content);
        self::assertSame([], $referenceStore->getAll());
    }

    public function testAVariantOfAnotherProductIsNoCurrentVariant(): void
    {
        $content = $this->resolveContent(
            $this->createVariantParentContent(),
            null,
            $this->createResolver(currentVariant: $this->createVariantContent(new Product('other-parent-uuid'))),
        );

        self::assertArrayNotHasKey('currentVariant', $content);
    }

    /** A variant tile asks for properties, gets the variant's own fields and loads no parent. */
    public function testAVariantReferenceResolvesAsItselfWithoutLoadingTheParent(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findOneBy');

        $content = $this->resolveContent(
            $this->createVariantContent(new Product('parent-uuid')),
            ['title' => 'title', 'url' => 'url'],
            $this->createResolver(productRepository: $productRepository),
        );

        self::assertSame('NC3FX-B', $this->contentOf($content['code']));
        self::assertArrayNotHasKey('currentVariant', $content);
    }

    public function testAProductWithVariantsHasNoCurrentVariant(): void
    {
        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findIdentifiersBy')->willReturn(['variant-uuid-1']);

        $content = $this->resolveContent(
            $this->createVariantParentContent(),
            null,
            $this->createResolver(productRepository: $productRepository),
        );

        self::assertArrayHasKey('variants', $content);
        self::assertArrayNotHasKey('currentVariant', $content);
    }

    /**
     * @return list<ResolvableResource>
     */
    private function resolvablesOf(ContentView $view): array
    {
        $resources = $view->getContent();
        self::assertIsArray($resources);
        self::assertContainsOnlyInstancesOf(ResolvableResource::class, $resources);

        /** @var list<ResolvableResource> $resources */
        return $resources;
    }

    private function contentOf(mixed $view): mixed
    {
        self::assertInstanceOf(ContentView::class, $view);

        return $view->getContent();
    }

    /**
     * @return ProductRepositoryInterface&Stub
     */
    private function createVariantRepository(): ProductRepositoryInterface
    {
        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findIdentifiersBy')->willReturn(['variant-uuid-1', 'variant-uuid-2']);

        return $productRepository;
    }

    private function createVariantContent(ProductInterface $parent): ProductDimensionContent
    {
        $variant = new Product('variant-uuid-2');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);

        $content = new ProductDimensionContent($variant);
        $content->setLocale('de');
        $content->setStage(DimensionContentInterface::STAGE_LIVE);
        $content->setTitle('NC3FX-B');
        $content->setCode('NC3FX-B');

        return $content;
    }

    private function createVariantParentContent(): ProductDimensionContent
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $content = new ProductDimensionContent($product);
        $content->setLocale('de');
        $content->setStage('draft');

        return $content;
    }
}
