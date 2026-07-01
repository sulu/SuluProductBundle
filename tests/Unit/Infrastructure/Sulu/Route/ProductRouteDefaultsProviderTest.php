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
use Sulu\Route\Application\Routing\Matcher\RouteDefaultsProviderInterface;
use Sulu\Route\Domain\Model\Route;
use Symfony\Component\DependencyInjection\Container;
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

    protected function getProductRouteDefaultsProviderInstance(): RouteDefaultsProviderInterface
    {
        return new ProductRouteDefaultsProvider(
            $this->productRepository->reveal(),
            $this->contentAggregator->reveal(),
            $this->metadataProviderRegistry,
            $this->cacheLifetimeResolver,
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
