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

namespace Sulu\Product\Tests\Functional\Content;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Content\Application\ContentResolver\ContentViewResolver\ContentViewResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Content\Tests\Functional\Traits\CreateMediaTrait;
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;
use Sulu\Product\Infrastructure\Sulu\Route\CurrentVariantProvider;
use Sulu\Product\Infrastructure\Sulu\Route\ProductRouteDefaultsProvider;
use Sulu\Route\Domain\Model\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(ProductResolver::class)]
#[CoversClass(CurrentVariantProvider::class)]
class ProductResolverVariantsTest extends SuluTestCase
{
    use CreateMediaTrait;

    private ContentResolverInterface $contentResolver;

    private ContentAggregatorInterface $contentAggregator;

    private EntityManagerInterface $entityManager;

    private ProductRepositoryInterface $productRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ContentResolverInterface $contentResolver */
        $contentResolver = $container->get('sulu_content.content_resolver');
        $this->contentResolver = $contentResolver;

        /** @var ContentAggregatorInterface $contentAggregator */
        $contentAggregator = $container->get('sulu_content.content_aggregator');
        $this->contentAggregator = $contentAggregator;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get('sulu_product.product_repository');
        $this->productRepository = $productRepository;

        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testVariantsAppearUnderRootProductVariantsAsFlatFieldsInPositionOrder(): void
    {
        $parent = $this->createParent();
        $this->createVariant($parent, 'NL4FX-5', 1);
        $this->createVariant($parent, 'NL4FX-4', 0, '/products/nl4fx-4');
        $this->entityManager->flush();

        $productData = $this->resolveProduct($this->aggregate($parent));

        self::assertArrayNotHasKey('url', $productData, 'a product with variants owns no route');
        self::assertArrayNotHasKey('currentVariant', $productData);

        $variants = $productData['variants'] ?? null;
        self::assertIsArray($variants);
        self::assertCount(2, $variants);

        $first = $variants[0] ?? null;
        $second = $variants[1] ?? null;
        self::assertIsArray($first);
        self::assertIsArray($second);

        self::assertSame(['title', 'url', 'code', 'status', 'position'], \array_keys($first), 'the bundle defaults as flat fields, no content envelope');
        self::assertSame('NL4FX-4 Variant', $first['title']);
        self::assertSame('/products/nl4fx-4', $first['url']);
        self::assertSame('NL4FX-4', $first['code']);
        self::assertSame(0, $first['position']);
        self::assertSame('NL4FX-5', $second['code']);
        self::assertNull($second['url'], 'a variant without a route has no url');
    }

    /** A variant URL renders its parent's content tab, with the parent as `product` and the variant as `currentVariant`. */
    public function testAVariantUrlResolvesItsParentWithTheVariantAsCurrentVariant(): void
    {
        $parent = $this->createParent();
        $variant = $this->createVariant($parent, 'NL4FX-4', 0, '/products/nl4fx-4');
        $this->createVariant($parent, 'NL4FX-5', 1);
        $this->entityManager->flush();

        $this->requestVariantUrl($this->aggregate($variant));
        $result = $this->contentResolver->resolve($this->aggregate($parent));

        $content = $result['content'];
        self::assertSame('NL4FX', $content['title'] ?? null, 'the content tab is the parent\'s');
        self::assertSame('Parent description', $content['description'] ?? null);

        $productData = $result['product'] ?? null;
        self::assertIsArray($productData);
        self::assertSame('NL4FX', $productData['title']);
        self::assertIsArray($productData['variants'] ?? null);
        self::assertCount(2, $productData['variants'], 'the parent lists every variant, the page\'s own included');

        $currentVariant = $productData['currentVariant'] ?? null;
        self::assertIsArray($currentVariant);
        self::assertSame('NL4FX-4 Variant', $currentVariant['title']);
        self::assertSame('NL4FX-4', $currentVariant['code']);
        self::assertSame('/products/nl4fx-4', $currentVariant['url']);
        self::assertArrayHasKey('attributes', $currentVariant);
        self::assertArrayNotHasKey('variants', $currentVariant, 'a variant is not itself a product with variants');
    }

    /** No enhancer lends a variant its parent's content any more, so a variant resolves as itself. */
    public function testAVariantResolvesAsItself(): void
    {
        $parent = $this->createParent();
        $variant = $this->createVariant($parent, 'NL4FX-4', 0, '/products/nl4fx-4');
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($this->aggregate($variant));

        self::assertSame('NL4FX-4 Variant', $result['content']['title'] ?? null);
        self::assertNull($result['content']['description'] ?? null, 'the parent\'s description stays with the parent');

        $productData = $result['product'] ?? null;
        self::assertIsArray($productData);
        self::assertSame('NL4FX-4 Variant', $productData['title']);
        self::assertArrayNotHasKey('currentVariant', $productData);
    }

    /** The reference index resolves without the enhancers, so a variant does not index its parent's associations and siblings. */
    public function testTheReferenceIndexResolvesAVariantAsItself(): void
    {
        $parent = $this->createParent();
        $variant = $this->createVariant($parent, 'NL4FX-4', 0);
        $this->createVariant($parent, 'NL4FX-5', 1);
        $this->entityManager->flush();

        /** @var ContentViewResolverInterface $contentViewResolver */
        $contentViewResolver = self::getContainer()->get('sulu_content.content_view_resolver');
        $productView = $contentViewResolver->getContentViews($this->aggregate($variant))['product'] ?? null;

        self::assertInstanceOf(ContentView::class, $productView);
        $productData = $productView->getContent();
        self::assertIsArray($productData);
        self::assertArrayNotHasKey('currentVariant', $productData);
        self::assertArrayNotHasKey('variants', $productData);

        $title = $productData['title'] ?? null;
        self::assertInstanceOf(ContentView::class, $title);
        self::assertSame('NL4FX-4 Variant', $title->getContent());
    }

    /** A parent whose draft was loaded earlier in the same request still resolves with its live content. */
    public function testProductParentResolvesTheLiveContentAfterTheParentDraftWasLoaded(): void
    {
        $parent = $this->createParent();
        $draftContent = $parent->createDimensionContent();
        $draftContent->setLocale('de');
        $draftContent->setStage('draft');
        $draftContent->setTemplateKey('product');
        $draftContent->setTemplateData(['title' => 'NL4FX draft', 'description' => 'Draft description']);
        $parent->addDimensionContent($draftContent);
        $this->entityManager->persist($draftContent);
        $variant = $this->createVariant($parent, 'NL4FX-4', 0);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loadedParent = $this->productRepository->findOneBy(
            ['uuid' => $parent->getUuid(), 'locale' => 'de', 'stage' => 'draft'],
            [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true],
        );
        self::assertNotNull($loadedParent);

        $loadedVariant = $this->productRepository->getOneBy(
            ['uuid' => $variant->getUuid(), 'locale' => 'de', 'stage' => 'live'],
            [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true],
        );

        $result = $this->contentResolver->resolve($this->aggregate($loadedVariant), ['parentTitle' => 'product.parent.title']);

        self::assertSame('NL4FX', $result['parentTitle'] ?? null);
    }

    /** A selection reaches the parent of a variant through `product.parent.`, next to the variant's own fields. */
    public function testAVariantResolvedAsAReferenceReachesItsParentThroughProductParent(): void
    {
        $parent = $this->createParent();
        $variant = $this->createVariant($parent, 'NL4FX-4', 0, '/products/nl4fx-4');
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($this->aggregate($variant), [
            'title' => 'product.title',
            'code' => 'product.code',
            'parentTitle' => 'product.parent.title',
            'parentCode' => 'product.parent.code',
        ]);

        self::assertSame('NL4FX-4 Variant', $result['title'] ?? null);
        self::assertSame('NL4FX-4', $result['code'] ?? null, 'the parent\'s fields do not overwrite the variant\'s');
        self::assertSame('NL4FX', $result['parentTitle'] ?? null);
        self::assertArrayHasKey('parentCode', $result);
        self::assertNull($result['parentCode'], 'the parent has no code');
    }

    /** A selection item maps its parent's fields after core replaced the parent reference, and `parent` stays a free key. */
    public function testAVariantAsASelectionItemReachesItsParentThroughProductParent(): void
    {
        $media = self::createMedia(self::createCollection());
        $this->entityManager->flush();
        $parent = $this->createParent(['image' => ['id' => $media->getId()]]);
        $variant = $this->createVariant($parent, 'NL4FX-4', 0, '/products/nl4fx-4');

        $product = $this->productRepository->createNew();
        $productContent = $product->createDimensionContent();
        $productContent->setLocale('de');
        $productContent->setStage('live');
        $productContent->setTemplateKey('product');
        $productContent->setTemplateData(['title' => 'NC3FXX']);
        $productContent->addAssociation(new ProductAssociation($productContent, $variant, 'suitable'));
        $product->addDimensionContent($productContent);
        $this->productRepository->add($product);
        $this->entityManager->persist($productContent);
        $this->entityManager->flush();

        $associations = $this->resolveProduct($this->aggregate($product))['associations'] ?? null;
        self::assertIsArray($associations);
        $suitable = $associations['suitable'] ?? null;
        self::assertIsArray($suitable);
        self::assertCount(1, $suitable);
        $item = $suitable[0];
        self::assertIsArray($item);

        self::assertSame('NL4FX-4 Variant', $item['title'] ?? null);
        self::assertSame('NL4FX', $item['parentTitle'] ?? null);
        self::assertSame('NL4FX-4', $item['parent'] ?? null, 'a template key named `parent` keeps its own value');
        $image = $item['parentImage'] ?? null;
        self::assertInstanceOf(Media::class, $image);
        self::assertSame($media->getId(), $image->getId());
    }

    public function testAParentReferenceFieldResolvesAtTheRoot(): void
    {
        $media = self::createMedia(self::createCollection());
        $this->entityManager->flush();
        $parent = $this->createParent(['image' => ['id' => $media->getId()]]);
        $variant = $this->createVariant($parent, 'NL4FX-4', 0);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($this->aggregate($variant), ['parentImage' => 'product.parent.image']);

        $image = $result['parentImage'] ?? null;
        self::assertInstanceOf(Media::class, $image);
        self::assertSame($media->getId(), $image->getId());
    }

    public function testAParentWithoutLiveContentResolvesProductParentAsNull(): void
    {
        $parent = $this->createParent();
        foreach ($parent->getDimensionContents() as $parentContent) {
            $parentContent->setStage('draft');
        }
        $variant = $this->createVariant($parent, 'NL4FX-4', 0);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($this->aggregate($variant), [
            'title' => 'product.title',
            'parentTitle' => 'product.parent.title',
        ]);

        self::assertSame('NL4FX-4 Variant', $result['title'] ?? null);
        self::assertArrayHasKey('parentTitle', $result);
        self::assertNull($result['parentTitle']);
    }

    public function testAProductWithoutParentResolvesProductParentAsNull(): void
    {
        $product = $this->productRepository->createNew();
        $content = $product->createDimensionContent();
        $content->setLocale('de');
        $content->setStage('live');
        $content->setTemplateKey('product');
        $content->setTemplateData(['title' => 'NC3FXX']);
        $product->addDimensionContent($content);
        $this->productRepository->add($product);
        $this->entityManager->persist($content);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($this->aggregate($product), [
            'title' => 'product.title',
            'parentTitle' => 'product.parent.title',
        ]);

        self::assertSame('NC3FXX', $result['title'] ?? null);
        self::assertArrayHasKey('parentTitle', $result);
        self::assertNull($result['parentTitle']);
    }

    /** Each requested parent name is a reference to the parent at its own key, sharing one load, so core loads and tags it with the other references. */
    public function testTheParentAddsOnlyTheRequestedKeys(): void
    {
        $parent = $this->createParent();
        $variant = $this->createVariant($parent, 'NL4FX-4', 0);
        $this->entityManager->flush();

        /** @var ContentViewResolverInterface $contentViewResolver */
        $contentViewResolver = self::getContainer()->get('sulu_content.content_view_resolver');
        $productView = $contentViewResolver->getContentViews(
            $this->aggregate($variant),
            ['parentTitle' => 'product.parent.title', 'parentCode' => 'product.parent.code'],
        )['product'] ?? null;

        self::assertInstanceOf(ContentView::class, $productView);
        $productData = $productView->getContent();
        self::assertIsArray($productData);
        self::assertSame(
            ['code', 'externalIdentifier', 'status', 'productFamily', 'position', 'image', 'shortDescription', 'parentTitle', 'parentCode'],
            \array_keys($productData),
        );

        $metadataIdentifiers = [];
        foreach (['parentTitle', 'parentCode'] as $key) {
            $parentView = $productData[$key];
            self::assertInstanceOf(ContentView::class, $parentView);
            $parentResource = $parentView->getContent();
            self::assertInstanceOf(ResolvableResource::class, $parentResource);
            self::assertSame($parent->getUuid(), $parentResource->getId());
            self::assertSame(
                ['properties' => ['parentTitle' => 'product.title', 'parentCode' => 'product.code']],
                $parentResource->getMetadata(),
            );
            self::assertSame($parent->getUuid(), $parentView->getReferences()[0]->getResourceId());
            $metadataIdentifiers[] = $parentResource->getMetadataIdentifier();
        }
        self::assertSame($metadataIdentifiers[0], $metadataIdentifiers[1], 'one load serves every parent key');

        $code = $productData['code'];
        self::assertInstanceOf(ContentView::class, $code);
        self::assertSame('NL4FX-4', $code->getContent());
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveProduct(ProductDimensionContentInterface $dimensionContent): array
    {
        $result = $this->contentResolver->resolve($dimensionContent);

        $productData = $result['product'] ?? null;
        self::assertIsArray($productData);

        /** @var array<string, mixed> $productData */
        return $productData;
    }

    /** The route defaults of a variant URL, as `ProductRouteDefaultsProvider` sets them. */
    private function requestVariantUrl(ProductDimensionContentInterface $variantContent): void
    {
        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push(new Request(attributes: [ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE => $variantContent]));
    }

    private function aggregate(ProductInterface $product): ProductDimensionContentInterface
    {
        return $this->contentAggregator->aggregate($product, ['locale' => 'de', 'stage' => 'live']);
    }

    /** @param array<string, mixed> $detailsData */
    private function createParent(array $detailsData = []): ProductInterface
    {
        $parent = $this->productRepository->createNew();
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $parentContent = $parent->createDimensionContent();
        $parentContent->setLocale('de');
        $parentContent->setStage('live');
        // TemplateResolver needs a registered template key to resolve the template section
        $parentContent->setTemplateKey('product');
        $parentContent->setTemplateData(['title' => 'NL4FX', 'description' => 'Parent description']);
        $parentContent->setDetailsData($detailsData);
        $parent->addDimensionContent($parentContent);

        $this->productRepository->add($parent);
        $this->entityManager->persist($parentContent);

        return $parent;
    }

    private function createVariant(ProductInterface $parent, string $code, int $position, ?string $slug = null): ProductInterface
    {
        $variant = $this->productRepository->createNew();
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);
        $variant->setPosition($position);

        $variantContent = $variant->createDimensionContent();
        $variantContent->setLocale('de');
        $variantContent->setStage('live');
        $variantContent->setTemplateKey('product');
        $variantContent->setCode($code);
        $variantContent->setTemplateData(['title' => $code . ' Variant']);

        // The route association carries no cascade, so it is persisted on its own.
        if (null !== $slug) {
            $route = new Route(ProductInterface::RESOURCE_KEY, $variant->getUuid(), 'de', $slug);
            $variantContent->setRoute($route);
            $this->entityManager->persist($route);
        }

        $variant->addDimensionContent($variantContent);

        $this->productRepository->add($variant);
        $this->entityManager->persist($variantContent);

        return $variant;
    }
}
