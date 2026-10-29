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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Route;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\CacheLifetimeMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataProvider;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TemplateMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeRequestStore;
use Sulu\Bundle\HttpCacheBundle\CacheLifetime\CacheLifetimeResolver;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Route\ProductRouteDefaultsProvider;
use Sulu\Product\Tests\Unit\UserInterface\Agent\Tool\Fixtures\FakeRouteGenerator;
use Sulu\Route\Application\Routing\Generator\RouteGeneratorInterface;
use Sulu\Route\Application\Routing\Matcher\RouteDefaultsProviderInterface;
use Sulu\Route\Domain\Exception\MissingRequestContextParameterException;
use Sulu\Route\Domain\Model\Route;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductRouteDefaultsProviderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;
    /** @var ObjectProphecy<ContentAggregatorInterface> */
    private ObjectProphecy $contentAggregator;
    private MetadataProviderRegistry $metadataProviderRegistry;
    private CacheLifetimeResolver $cacheLifetimeResolver;
    /** @var ObjectProphecy<FormMetadataProvider> */
    private ObjectProphecy $formMetadataProvider;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentAggregator = $this->prophesize(ContentAggregatorInterface::class);
        $this->cacheLifetimeResolver = new CacheLifetimeResolver();
        $this->formMetadataProvider = $this->prophesize(FormMetadataProvider::class);
        $container = new Container();
        $container->set('form', $this->formMetadataProvider->reveal());
        $this->metadataProviderRegistry = new MetadataProviderRegistry($container);
    }

    protected function getProductRouteDefaultsProviderInstance(?Request $request = null, ?string $variantQueryParameter = null, ?RouteGeneratorInterface $routeGenerator = null): RouteDefaultsProviderInterface
    {
        $requestStack = new RequestStack();
        if (null !== $request) {
            $requestStack->push($request);
        }

        return new ProductRouteDefaultsProvider(
            $this->productRepository->reveal(),
            $this->contentAggregator->reveal(),
            $this->metadataProviderRegistry,
            $this->cacheLifetimeResolver,
            $routeGenerator ?? new FakeRouteGenerator(),
            $requestStack,
            $variantQueryParameter,
        );
    }

    public function testGetDefaults(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $locale = 'en';
        $slug = '/test-product';

        $product = new Product('123-123-123');
        $resolvedDimensionContent = new ProductDimensionContent($product);
        $resolvedDimensionContent->setLocale($locale);
        $resolvedDimensionContent->setTemplateKey('default');

        $this->productRepository->findOneBy(
            [
                'uuid' => '123-123-123',
            ],
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    'dimensionAttributes' => [
                        'locale' => $locale,
                        'stage' => DimensionContentInterface::STAGE_LIVE,
                        'version' => DimensionContentInterface::CURRENT_VERSION,
                    ],
                    'selects' => [
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_TAGS => true,
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_CATEGORIES => true,
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_CATEGORIES_TRANSLATION => true,
                    ],
                ],
                ProductRepositoryInterface::SELECT_PRODUCT_ATTRIBUTES => true,
            ]
        )->willReturn($product);

        $this->contentAggregator->aggregate($product, ['locale' => $locale, 'stage' => 'live', 'version' => 0])
            ->willReturn($resolvedDimensionContent);

        $this->prepareTemplateMetadata('ProductController::indexAction', 'product.html.twig', 'seconds', '3600');

        $route = new Route(
            ProductInterface::RESOURCE_KEY,
            '123-123-123',
            $locale,
            $slug,
        );

        $result = $provider->getDefaults($route);

        $this->assertArrayNotHasKey('_seo', $result);
        $this->assertArrayNotHasKey(ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE, $result);
        $this->assertSame($resolvedDimensionContent, $result['object']);
        $this->assertSame('product.html.twig', $result['view']);
        $this->assertSame('ProductController::indexAction', $result['_controller']);
    }

    public function testGetDefaultsNotFound(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $this->productRepository->findOneBy(Argument::cetera())->willReturn(null);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));
    }

    public function testGetDefaultsReturnsAggregatedContentForShadowLocaleRoute(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $routeLocale = 'de';
        $contentLocale = 'en';
        $slug = '/deutsches-produkt';

        $product = new Product('123-123-123');
        $resolvedDimensionContent = new ProductDimensionContent($product);
        $resolvedDimensionContent->setLocale($contentLocale);
        $resolvedDimensionContent->setTemplateKey('default');

        $this->productRepository->findOneBy(
            [
                'uuid' => '123-123-123',
            ],
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    'dimensionAttributes' => [
                        'locale' => $routeLocale,
                        'stage' => DimensionContentInterface::STAGE_LIVE,
                        'version' => DimensionContentInterface::CURRENT_VERSION,
                    ],
                    'selects' => [
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_TAGS => true,
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_CATEGORIES => true,
                        DimensionContentQueryEnhancer::SELECT_EXCERPT_CATEGORIES_TRANSLATION => true,
                    ],
                ],
                ProductRepositoryInterface::SELECT_PRODUCT_ATTRIBUTES => true,
            ]
        )->willReturn($product);

        $this->contentAggregator->aggregate($product, ['locale' => $routeLocale, 'stage' => 'live', 'version' => 0])
            ->willReturn($resolvedDimensionContent);

        $this->prepareTemplateMetadata(
            'ProductController::indexAction',
            'product.html.twig',
            'seconds',
            '3600',
            $contentLocale,
        );

        $route = new Route(
            ProductInterface::RESOURCE_KEY,
            '123-123-123',
            $routeLocale,
            $slug,
        );

        $result = $provider->getDefaults($route);

        $this->assertSame($resolvedDimensionContent, $result['object']);
        $this->assertSame('product.html.twig', $result['view']);
        $this->assertSame('ProductController::indexAction', $result['_controller']);
    }

    public function testGetDefaultsThrowsWhenContentNotFound(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $product = new Product('123-123-123');

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willThrow(ContentNotFoundException::class);

        $this->expectException(NotFoundHttpException::class);

        $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));
    }

    /** A variant has no content of its own, its URL renders its product and carries the variant along. */
    public function testGetDefaultsRendersTheParentOfAVariant(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $parent = new Product('parent-uuid');
        $variant = new Product('123-123-123');
        $variant->setParent($parent);

        $variantContent = new ProductDimensionContent($variant);
        $variantContent->setLocale('en');

        $parentContent = new ProductDimensionContent($parent);
        $parentContent->setLocale('en');
        $parentContent->setTemplateKey('default');

        $this->productRepository->findOneBy(['uuid' => '123-123-123'], Argument::type('array'))->willReturn($variant);
        $this->contentAggregator->aggregate($variant, ['locale' => 'en', 'stage' => 'live', 'version' => 0])
            ->willReturn($variantContent);

        $this->productRepository->findOneBy(['uuid' => 'parent-uuid'], Argument::type('array'))->willReturn($parent);
        $this->contentAggregator->aggregate($parent, ['locale' => 'en', 'stage' => 'live', 'version' => 0])
            ->willReturn($parentContent);

        $this->prepareTemplateMetadata('ProductController::indexAction', 'product.html.twig', 'seconds', '3600');

        $result = $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-variant'));

        $this->assertSame($parentContent, $result['object']);
        $this->assertSame($variantContent, $result[ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE]);
        $this->assertSame('product.html.twig', $result['view']);
        $this->assertSame('ProductController::indexAction', $result['_controller']);
    }

    public function testGetDefaultsThrowsForVariantWhoseParentIsNotLive(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $parent = new Product('parent-uuid');
        $variant = new Product('123-123-123');
        $variant->setParent($parent);

        $variantContent = new ProductDimensionContent($variant);
        $variantContent->setLocale('en');

        $this->productRepository->findOneBy(['uuid' => '123-123-123'], Argument::type('array'))->willReturn($variant);
        $this->contentAggregator->aggregate($variant, Argument::type('array'))
            ->willReturn($variantContent);

        $this->productRepository->findOneBy(['uuid' => 'parent-uuid'], Argument::type('array'))->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('No published parent product found for variant id "123-123-123" and locale "en"');

        $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-variant'));
    }

    public function testGetDefaultsRendersTheVariantNamedByTheQueryParameter(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance(new Request(query: ['variant' => 'RED']), 'variant');

        [$product, $productContent] = $this->prepareProduct();
        $variant = new Product('variant-uuid');
        $variant->setParent($product);
        $variantContent = new ProductDimensionContent($variant);

        $dimensionAttributes = ['locale' => 'en', 'stage' => 'live', 'version' => 0];
        $this->productRepository->findBy(
            ['parent' => '123-123-123', 'code' => 'RED', 'locale' => 'en', 'stage' => 'live'],
            [],
            [ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => ['dimensionAttributes' => $dimensionAttributes]],
        )->willReturn([$variant])->shouldBeCalledOnce();
        $this->contentAggregator->aggregate($variant, $dimensionAttributes)->willReturn($variantContent);

        $result = $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));

        $this->assertSame($productContent, $result['object']);
        $this->assertSame($variantContent, $result[ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE]);
    }

    public function testGetDefaultsReadsTheConfiguredQueryParameter(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance(new Request(query: ['variant' => 'RED', 'sku' => 'BLUE']), 'sku');

        $this->prepareProduct();
        $this->productRepository->findBy(['parent' => '123-123-123', 'code' => 'BLUE', 'locale' => 'en', 'stage' => 'live'], [], Argument::type('array'))
            ->willReturn([])->shouldBeCalledOnce();

        $result = $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));

        $this->assertArrayNotHasKey(ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE, $result);
    }

    public function testGetDefaultsIgnoresAVariantWithoutPublishedContent(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance(new Request(query: ['variant' => 'RED']), 'variant');

        [$product] = $this->prepareProduct();
        $variant = new Product('variant-uuid');
        $variant->setParent($product);
        $this->productRepository->findBy(Argument::cetera())->willReturn([$variant]);
        $this->contentAggregator->aggregate($variant, Argument::type('array'))->willThrow(ContentNotFoundException::class);

        $result = $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));

        $this->assertArrayNotHasKey(ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE, $result);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideQueriesWithoutAVariant(): iterable
    {
        yield 'no parameter' => [[]];
        yield 'empty code' => [['variant' => '']];
        yield 'array' => [['variant' => ['RED']]];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('provideQueriesWithoutAVariant')]
    public function testGetDefaultsWithoutAUsableQueryParameterLoadsNoVariant(array $query): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance(new Request(query: $query), 'variant');

        $this->prepareProduct();
        $this->productRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $result = $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));

        $this->assertArrayNotHasKey(ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE, $result);
    }

    public function testGetDefaultsWithoutARequestLoadsNoVariant(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance(null, 'variant');

        $this->prepareProduct();
        $this->productRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $result = $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));

        $this->assertArrayNotHasKey(ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE, $result);
    }

    public function testGetDefaultsIgnoresTheQueryParameterWhenVariantsHaveRoutes(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance(new Request(query: ['variant' => 'RED']), null);

        $this->prepareProduct();
        $this->productRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $result = $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));

        $this->assertArrayNotHasKey(ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE, $result);
        $this->assertArrayNotHasKey('_seo', $result);
    }

    public function testGetDefaultsSetsTheProductUrlAsCanonicalWhenAProductUrlTakesAQueryParameter(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance(new Request(), 'variant');

        $this->prepareProduct();

        $result = $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));

        $this->assertSame(['canonicalUrl' => 'https://example.org/en/test-product'], $result['_seo']);
    }

    public function testGetDefaultsLeavesOutTheCanonicalUrlWhenNoUrlCanBeGenerated(): void
    {
        $routeGenerator = $this->createStub(RouteGeneratorInterface::class);
        $routeGenerator->method('generate')->willThrowException(new MissingRequestContextParameterException('host'));
        $provider = $this->getProductRouteDefaultsProviderInstance(new Request(), 'variant', $routeGenerator);

        $this->prepareProduct();

        $result = $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));

        $this->assertArrayNotHasKey('_seo', $result);
    }

    public function testGetDefaultsKeepsTheCanonicalUrlTheEditorSet(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance(new Request(), 'variant');

        [, $productContent] = $this->prepareProduct();
        $productContent->setSeoData(['canonicalUrl' => 'https://example.org/own']);

        $result = $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));

        $this->assertArrayNotHasKey('_seo', $result);
    }

    public function testGetDefaultsThrowsWhenNoContentLocale(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $product = new Product('123-123-123');
        $dimensionContent = new ProductDimensionContent($product);
        // locale is not set, so getLocale() returns null

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent);

        $this->expectException(NotFoundHttpException::class);

        $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));
    }

    public function testGetDefaultsThrowsWhenNoTemplateKey(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $product = new Product('123-123-123');
        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setLocale('en');
        // template key is not set

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent);

        $this->expectException(NotFoundHttpException::class);

        $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));
    }

    public function testGetDefaultsThrowsWhenMetadataNotTypedFormMetadata(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $product = new Product('123-123-123');
        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setLocale('en');
        $dimensionContent->setTemplateKey('default');

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent);

        // Return a MetadataInterface that is NOT TypedFormMetadata
        $nonTypedMetadata = $this->prophesize(MetadataInterface::class);
        $this->formMetadataProvider->getMetadata(ProductInterface::TEMPLATE_TYPE, Argument::type('string'), [])
            ->willReturn($nonTypedMetadata->reveal());

        $this->expectException(\RuntimeException::class);

        $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));
    }

    public function testGetDefaultsThrowsWhenFormNotFound(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $product = new Product('123-123-123');
        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setLocale('en');
        $dimensionContent->setTemplateKey('nonexistent');

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent);

        // TypedFormMetadata with no forms added
        $typedMetadata = new TypedFormMetadata();
        $this->formMetadataProvider->getMetadata(ProductInterface::TEMPLATE_TYPE, Argument::type('string'), [])
            ->willReturn($typedMetadata);

        $this->expectException(\RuntimeException::class);

        $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));
    }

    public function testGetDefaultsThrowsWhenTemplateMetadataNotSet(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $product = new Product('123-123-123');
        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setLocale('en');
        $dimensionContent->setTemplateKey('default');

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent);

        // FormMetadata with template explicitly set to null
        $typedMetadata = new TypedFormMetadata();
        $formMetadata = new FormMetadata();
        $formMetadata->setKey('default');
        $formMetadata->setTemplate(null);
        $typedMetadata->addForm($formMetadata->getKey(), $formMetadata);

        $this->formMetadataProvider->getMetadata(ProductInterface::TEMPLATE_TYPE, Argument::type('string'), [])
            ->willReturn($typedMetadata);

        $this->expectException(\RuntimeException::class);

        $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', 'en', '/test-product'));
    }

    public function testGetDefaultsWithCacheLifetime(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $locale = 'en';
        $slug = '/test-product';

        $product = new Product('123-123-123');
        $resolvedDimensionContent = new ProductDimensionContent($product);
        $resolvedDimensionContent->setLocale($locale);
        $resolvedDimensionContent->setTemplateKey('default');

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($resolvedDimensionContent);

        $typedMetadata = new TypedFormMetadata();
        $formMetadata = new FormMetadata();
        $formMetadata->setKey('default');
        $typedMetadata->addForm($formMetadata->getKey(), $formMetadata);

        $templateMetadata = new TemplateMetadata('ProductController::indexAction', 'product.html.twig');
        $templateMetadata->setCacheLifetime(new CacheLifetimeMetadata('seconds', '3600'));
        $formMetadata->setTemplate($templateMetadata);

        $this->formMetadataProvider->getMetadata(ProductInterface::TEMPLATE_TYPE, Argument::type('string'), [])
            ->willReturn($typedMetadata);

        $route = new Route(ProductInterface::RESOURCE_KEY, '123-123-123', $locale, $slug);
        $result = $provider->getDefaults($route);

        $this->assertArrayHasKey(CacheLifetimeRequestStore::ATTRIBUTE_KEY, $result);
        $this->assertSame(3600, $result[CacheLifetimeRequestStore::ATTRIBUTE_KEY]);
        $this->assertSame($resolvedDimensionContent, $result['object']);
        $this->assertSame('product.html.twig', $result['view']);
        $this->assertSame('ProductController::indexAction', $result['_controller']);
    }

    public function testGetDefaultsThrowsOnUnsupportedCacheLifetimeType(): void
    {
        $provider = $this->getProductRouteDefaultsProviderInstance();

        $locale = 'en';
        $slug = '/test-product';

        $product = new Product('123-123-123');
        $resolvedDimensionContent = new ProductDimensionContent($product);
        $resolvedDimensionContent->setLocale($locale);
        $resolvedDimensionContent->setTemplateKey('default');

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($resolvedDimensionContent);

        $typedMetadata = new TypedFormMetadata();
        $formMetadata = new FormMetadata();
        $formMetadata->setKey('default');
        $typedMetadata->addForm($formMetadata->getKey(), $formMetadata);

        // Use a type that CacheLifetimeResolver does NOT support (not 'seconds' or 'expression')
        $templateMetadata = new TemplateMetadata('ProductController::indexAction', 'product.html.twig');
        $templateMetadata->setCacheLifetime(new CacheLifetimeMetadata('invalid_type', '100'));
        $formMetadata->setTemplate($templateMetadata);

        $this->formMetadataProvider->getMetadata(ProductInterface::TEMPLATE_TYPE, Argument::type('string'), [])
            ->willReturn($typedMetadata);

        $this->expectException(\InvalidArgumentException::class);

        $provider->getDefaults(new Route(ProductInterface::RESOURCE_KEY, '123-123-123', $locale, $slug));
    }

    /**
     * @return array{Product, ProductDimensionContent} the live product "123-123-123" and its content in "en"
     */
    private function prepareProduct(): array
    {
        $product = new Product('123-123-123');
        $productContent = new ProductDimensionContent($product);
        $productContent->setLocale('en');
        $productContent->setTemplateKey('default');

        $this->productRepository->findOneBy(['uuid' => '123-123-123'], Argument::type('array'))->willReturn($product);
        $this->contentAggregator->aggregate($product, ['locale' => 'en', 'stage' => 'live', 'version' => 0])->willReturn($productContent);
        $this->prepareTemplateMetadata('ProductController::indexAction', 'product.html.twig', 'seconds', '3600');

        return [$product, $productContent];
    }

    private function prepareTemplateMetadata(
        string $controller,
        string $view,
        string $cacheLifeTimeType,
        string $cacheLifeTimeValue,
        ?string $locale = null,
    ): void {
        $typedMetadata = new TypedFormMetadata();
        $formMetadata = new FormMetadata();
        $formMetadata->setKey('default');
        $typedMetadata->addForm($formMetadata->getKey(), $formMetadata);

        $templateMetadata = new TemplateMetadata($controller, $view);
        $formMetadata->setTemplate($templateMetadata);

        $localeArgument = $locale ?? Argument::type('string');

        $this->formMetadataProvider->getMetadata(ProductInterface::TEMPLATE_TYPE, $localeArgument, [])
            ->willReturn($typedMetadata)
            ->shouldBeCalled();
    }
}
