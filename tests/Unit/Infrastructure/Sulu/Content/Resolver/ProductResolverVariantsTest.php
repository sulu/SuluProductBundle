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
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;
use Sulu\Route\Domain\Model\Route;

#[CoversClass(ProductResolver::class)]
class ProductResolverVariantsTest extends ProductResolverTestCase
{
    public function testOmitsVariantsWhenResolvedAsAReference(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findBy');

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
        $productRepository->expects(self::never())->method('findBy');

        $product = new ProductDimensionContent(new Product());
        $product->setLocale('de');
        $product->setStage('draft');

        $content = $this->resolveContent($product, null, $this->createResolver(productRepository: $productRepository));

        self::assertArrayNotHasKey('variants', $content);
    }

    public function testOmitsVariantsWhenTheLoaderFindsNone(): void
    {
        $content = $this->resolveContent($this->createVariantParentContent());

        self::assertArrayNotHasKey('variants', $content);
    }

    /** One query loads the variants with their content, in position order. */
    public function testEmitsOneFlatEntryPerVariantInTheRepositorysOrder(): void
    {
        $variant1 = $this->createVariant('variant-uuid-1', 'NC3FX');
        $variant2 = $this->createVariant('variant-uuid-2', 'NC3FX-B');

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())
            ->method('findBy')
            ->with(
                ['parent' => 'parent-uuid', 'locale' => 'de', 'stage' => DimensionContentInterface::STAGE_LIVE],
                ['position' => 'asc', 'created' => 'asc', 'uuid' => 'asc'],
                [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true],
            )
            ->willReturn([$variant1->getResource(), $variant2->getResource()]);

        $content = $this->resolveContent(
            $this->createVariantParentContent(),
            null,
            $this->createResolver(productRepository: $productRepository, contentAggregator: $this->aggregatorFor($variant1, $variant2)),
        );

        $entries = $this->variantEntries($content);
        self::assertCount(2, $entries);
        self::assertSame('NC3FX', $this->contentOf($entries[0]['title']));
        self::assertSame('NC3FX-B', $this->contentOf($entries[1]['title']));
        self::assertSame('/product/nc3fx-b', $this->contentOf($entries[1]['url']));
        self::assertSame('NC3FX-B', $this->contentOf($entries[1]['code']));
        self::assertSame(['title', 'url', 'code', 'status', 'position'], \array_keys($entries[1]));
        self::assertArrayNotHasKey('variants', $entries[1]);

        $variants = $content['variants'];
        self::assertInstanceOf(ContentView::class, $variants);
        self::assertCount(2, $variants->getReferences());
    }

    public function testEntriesCarryTheConfiguredProperties(): void
    {
        $variant = $this->createVariant('variant-uuid-1', 'NC3FX');

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findBy')->willReturn([$variant->getResource()]);

        $entries = $this->variantEntries($this->resolveContent(
            $this->createVariantParentContent(),
            null,
            $this->createResolver(
                productRepository: $productRepository,
                contentAggregator: $this->aggregatorFor($variant),
                variantProperties: ['code' => 'product.code', 'attributes' => 'product.attributes', 'subtitle' => 'subtitle'],
            ),
        ));

        self::assertSame(['code', 'attributes'], \array_keys($entries[0]), 'only product properties resolve here');
    }

    public function testSkipsAVariantWithoutContentInTheLocale(): void
    {
        $variant = $this->createVariant('variant-uuid-1', 'NC3FX');

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findBy')->willReturn([$variant->getResource(), new Product('variant-uuid-2')]);

        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willReturnCallback(
            static fn (ProductInterface $product): ProductDimensionContent => 'variant-uuid-1' === $product->getUuid()
                ? $variant
                : throw new ContentNotFoundException($product, []),
        );

        $entries = $this->variantEntries($this->resolveContent(
            $this->createVariantParentContent(),
            null,
            $this->createResolver(productRepository: $productRepository, contentAggregator: $contentAggregator),
        ));

        self::assertCount(1, $entries);
    }

    /** A variant page resolves its parent as `product` and itself as `currentVariant`. */
    public function testAVariantResolvesItsParentAndItselfAsCurrentVariant(): void
    {
        $parentContent = $this->createVariantParentContent();
        $parentContent->setTitle('NC3FX');

        $variantContent = $this->createVariantContent($parentContent->getResource());

        $productRepository = $this->createVariantRepository();
        $productRepository->method('findOneBy')->willReturn($parentContent->getResource());

        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willReturn($parentContent);

        $content = $this->resolveContent(
            $variantContent,
            null,
            $this->createResolver(productRepository: $productRepository, contentAggregator: $contentAggregator),
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

    /** A variant page renders its parent's data, so publishing the parent must clear it. */
    public function testAVariantPageIsTaggedWithItsParent(): void
    {
        $parent = new Product('01a0a3fe-4a32-77dc-bbce-312e6926f731');
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $parentContent = new ProductDimensionContent($parent);
        $parentContent->setLocale('de');

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findOneBy')->willReturn($parent);
        $productRepository->method('findBy')->willReturn([]);

        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willReturn($parentContent);

        $referenceStore = new ReferenceStore();
        $this->resolveContent(
            $this->createVariantContent($parent),
            null,
            $this->createResolver(productRepository: $productRepository, contentAggregator: $contentAggregator, referenceStore: $referenceStore),
        );

        self::assertContains('01a0a3fe-4a32-77dc-bbce-312e6926f731', $referenceStore->getAll());
    }

    /** A page lists its variants' titles, urls and images, so publishing one of them must clear it. */
    public function testEveryListedVariantIsTagged(): void
    {
        $variant1 = $this->createVariant('01a0a3fe-5504-74cd-b9aa-2296395bb1b1', 'NC3FX');
        $variant2 = $this->createVariant('01a0a3fe-5504-74cd-b9aa-2296395bb1b2', 'NC3FX-B');

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findBy')->willReturn([$variant1->getResource(), $variant2->getResource()]);

        $referenceStore = new ReferenceStore();
        $this->resolveContent(
            $this->createVariantParentContent(),
            null,
            $this->createResolver(
                productRepository: $productRepository,
                contentAggregator: $this->aggregatorFor($variant1, $variant2),
                referenceStore: $referenceStore,
            ),
        );

        self::assertEqualsCanonicalizing(
            ['01a0a3fe-5504-74cd-b9aa-2296395bb1b1', '01a0a3fe-5504-74cd-b9aa-2296395bb1b2'],
            \array_values($referenceStore->getAll()),
        );
    }

    public function testAReferenceTagsNoParent(): void
    {
        $referenceStore = new ReferenceStore();
        $this->resolveContent(
            $this->createVariantContent(new Product('01a0a3fe-4a32-77dc-bbce-312e6926f731')),
            ['title' => 'product.title'],
            $this->createResolver(referenceStore: $referenceStore),
        );

        self::assertSame([], $referenceStore->getAll());
    }

    public function testAVariantWhoseParentIsNotLoadableResolvesAsItself(): void
    {
        $content = $this->resolveContent($this->createVariantContent(new Product('parent-uuid')));

        self::assertSame('NC3FX-B', $this->contentOf($content['title']));
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
        $variant = $this->createVariant('variant-uuid-1', 'NC3FX');

        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findBy')->willReturn([$variant->getResource()]);

        $content = $this->resolveContent(
            $this->createVariantParentContent(),
            null,
            $this->createResolver(productRepository: $productRepository, contentAggregator: $this->aggregatorFor($variant)),
        );

        self::assertArrayHasKey('variants', $content);
        self::assertArrayNotHasKey('currentVariant', $content);
    }

    /** Live whatever the page is on, so the list only shows published variants. */
    public function testAsksForTheLiveStageWhateverThePageIsOn(): void
    {
        $content = $this->createVariantParentContent();
        $content->setStage(DimensionContentInterface::STAGE_DRAFT);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())
            ->method('findBy')
            ->with(
                self::callback(static fn (array $filters): bool => 'de' === $filters['locale']
                    && DimensionContentInterface::STAGE_LIVE === $filters['stage']),
            )
            ->willReturn([new Product('variant-uuid-1')]);

        $this->resolveContent($content, null, $this->createResolver(productRepository: $productRepository));
    }

    /**
     * @param mixed[] $content
     *
     * @return list<array<string, ContentView>>
     */
    private function variantEntries(array $content): array
    {
        $variants = $this->contentOf($content['variants']);
        self::assertIsArray($variants);

        $entries = [];
        foreach ($variants as $entry) {
            $fields = $this->contentOf($entry);
            self::assertIsArray($fields);
            /** @var array<string, ContentView> $fields */
            $entries[] = $fields;
        }

        return $entries;
    }

    private function createVariant(string $uuid, string $code): ProductDimensionContent
    {
        $variant = new Product($uuid);
        $variant->setType(ProductInterface::TYPE_VARIANT);

        $content = new ProductDimensionContent($variant);
        $content->setLocale('de');
        $content->setTitle($code);
        $content->setCode($code);
        $content->setRoute(new Route(ProductInterface::RESOURCE_KEY, $uuid, 'de', '/product/' . \strtolower($code)));

        return $content;
    }

    private function aggregatorFor(ProductDimensionContent ...$contents): ContentAggregatorInterface
    {
        $byUuid = [];
        foreach ($contents as $content) {
            $byUuid[$content->getResource()->getUuid()] = $content;
        }

        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willReturnCallback(
            static fn (ProductInterface $product): ProductDimensionContent => $byUuid[$product->getUuid()],
        );

        return $contentAggregator;
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
        $productRepository->method('findBy')->willReturn([new Product('variant-uuid-1'), new Product('variant-uuid-2')]);

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
