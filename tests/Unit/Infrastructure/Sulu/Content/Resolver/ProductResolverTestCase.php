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

use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStore;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\MetadataResolver\MetadataResolver;
use Sulu\Content\Application\PropertyResolver\PropertyResolverProviderInterface;
use Sulu\Content\Application\PropertyResolver\Resolver\DefaultPropertyResolver;
use Sulu\Product\Application\Attribute\AttributeValueConverter;
use Sulu\Product\Application\Routing\PerProductSlugProvider;
use Sulu\Product\Application\Routing\PerVariantSlugProvider;
use Sulu\Product\Application\Routing\ProductSlugProviderInterface;
use Sulu\Product\Application\Routing\VariantUrlBuilder;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;
use Sulu\Product\Infrastructure\Sulu\Route\CurrentVariantProvider;
use Sulu\Product\Infrastructure\Sulu\Route\ProductRouteDefaultsProvider;
use Sulu\Product\Tests\Unit\Support\AttributeTypes;
use Sulu\Product\Tests\Unit\Support\ProductAttributeFragmentLoader;
use Sulu\Route\Domain\Model\Route;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * One resolver builds every section, so a test for one section still has to supply the
 * collaborators of the others. The defaults here make each of them resolve to nothing.
 */
abstract class ProductResolverTestCase extends TestCase
{
    /**
     * @param array<string, string> $variantProperties
     * @param ProductDimensionContentInterface|null $currentVariant the variant whose URL the request renders
     */
    protected function createResolver(
        ?FormMetadataLoaderInterface $formMetadataLoader = null,
        ?MetadataProviderInterface $formMetadataProvider = null,
        ?MetadataResolver $metadataResolver = null,
        ?ProductRepositoryInterface $productRepository = null,
        ?ReferenceStoreInterface $referenceStore = null,
        array $variantProperties = ['title' => 'product.title', 'url' => 'product.url', 'code' => 'product.code', 'status' => 'product.status', 'position' => 'product.position'],
        ?ProductDimensionContentInterface $currentVariant = null,
        ?AttributeValueConverter $attributeValueConverter = null,
        ?ProductSlugProviderInterface $slugProvider = null,
    ): ProductResolver {
        $requestStack = new RequestStack();
        $requestStack->push(new Request(attributes: [ProductRouteDefaultsProvider::VARIANT_ATTRIBUTE => $currentVariant]));

        return new ProductResolver(
            $formMetadataLoader ?? $this->noDetailFields(),
            $formMetadataProvider ?? $this->noAssociationFields(),
            $metadataResolver ?? $this->resolvingByDefault(),
            $productRepository ?? $this->noVariants(),
            new CurrentVariantProvider($requestStack),
            $referenceStore ?? new ReferenceStore(),
            $attributeValueConverter ?? ProductAttributeFragmentLoader::converter(),
            AttributeTypes::formatter(),
            $slugProvider ?? new PerVariantSlugProvider(),
            $variantProperties,
        );
    }

    protected function createPerProductSlugProvider(?string $productSlug = null): ProductSlugProviderInterface
    {
        $routeRepository = $this->createStub(RouteRepositoryInterface::class);
        $routeRepository->method('findFirstBy')->willReturn(
            null === $productSlug ? null : new Route('products', 'parent-uuid', 'en', $productSlug),
        );

        return new PerProductSlugProvider($routeRepository, new VariantUrlBuilder('variant'));
    }

    /**
     * The whole resolved `product` namespace.
     *
     * @param array<string, string>|null $properties
     *
     * @return mixed[] keyed by resolver property name
     */
    protected function resolveContent(
        ProductDimensionContentInterface $dimensionContent,
        ?array $properties = null,
        ?ProductResolver $resolver = null,
    ): array {
        $result = ($resolver ?? $this->createResolver())->resolve($dimensionContent, $properties);
        self::assertInstanceOf(ContentView::class, $result);

        $content = $result->getContent();
        self::assertIsArray($content);

        return $content;
    }

    protected function noDetailFields(): FormMetadataLoaderInterface
    {
        $loader = $this->createStub(FormMetadataLoaderInterface::class);
        $loader->method('getMetadata')->willReturn(null);

        return $loader;
    }

    protected function noAssociationFields(): MetadataProviderInterface
    {
        $provider = $this->createStub(MetadataProviderInterface::class);
        $provider->method('getMetadata')->willReturn(new FormMetadata());

        return $provider;
    }

    /**
     * Every field type resolves to its data, as when no property resolver is registered for it.
     */
    protected function resolvingByDefault(): MetadataResolver
    {
        $provider = $this->createStub(PropertyResolverProviderInterface::class);
        $provider->method('getPropertyResolver')->willReturn(new DefaultPropertyResolver());

        return new MetadataResolver($provider);
    }

    protected function noVariants(): ProductRepositoryInterface
    {
        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('findBy')->willReturn([]);
        $repository->method('findIdentifiersBy')->willReturn([]);

        return $repository;
    }
}
