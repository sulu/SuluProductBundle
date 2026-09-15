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
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ContentEnhancer\ProductVariantDimensionContentEnhancer;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;
use Sulu\Route\Domain\Model\Route;

#[CoversClass(ProductResolver::class)]
#[CoversClass(ProductVariantDimensionContentEnhancer::class)]
class ProductResolverVariantsTest extends SuluTestCase
{
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

    /** A variant page renders its parent's content tab, with the parent as `product` and itself as `currentVariant`. */
    public function testAVariantResolvesItsParentAndItselfAsCurrentVariant(): void
    {
        $parent = $this->createParent();
        $variant = $this->createVariant($parent, 'NL4FX-4', 0, '/products/nl4fx-4');
        $this->createVariant($parent, 'NL4FX-5', 1);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($this->aggregate($variant));

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

    private function aggregate(ProductInterface $product): ProductDimensionContentInterface
    {
        return $this->contentAggregator->aggregate($product, ['locale' => 'de', 'stage' => 'live']);
    }

    private function createParent(): ProductInterface
    {
        $parent = $this->productRepository->createNew();
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $parentContent = $parent->createDimensionContent();
        $parentContent->setLocale('de');
        $parentContent->setStage('live');
        // TemplateResolver needs a registered template key to resolve the template section
        $parentContent->setTemplateKey('product');
        $parentContent->setTemplateData(['title' => 'NL4FX', 'description' => 'Parent description']);
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
