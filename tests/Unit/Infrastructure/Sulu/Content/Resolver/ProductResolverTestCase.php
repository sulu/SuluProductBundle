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

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStore;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\MetadataResolver\MetadataResolver;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductParentContentLoader;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;

/**
 * One resolver builds every section, so a test for one section still has to supply the
 * collaborators of the others. The defaults here make each of them resolve to nothing.
 */
abstract class ProductResolverTestCase extends TestCase
{
    /**
     * @param array<string, string> $variantProperties
     */
    protected function createResolver(
        ?FormMetadataLoaderInterface $formMetadataLoader = null,
        ?MetadataProviderInterface $formMetadataProvider = null,
        ?MetadataResolver $metadataResolver = null,
        ?ProductRepositoryInterface $productRepository = null,
        ?ContentAggregatorInterface $contentAggregator = null,
        ?ReferenceStoreInterface $referenceStore = null,
        array $variantProperties = ['title' => 'product.title', 'url' => 'product.url', 'code' => 'product.code', 'status' => 'product.status', 'position' => 'product.position'],
    ): ProductResolver {
        $productRepository ??= $this->noVariants();
        $contentAggregator ??= $this->emptyContents();

        return new ProductResolver(
            $formMetadataLoader ?? $this->noDetailFields(),
            $formMetadataProvider ?? $this->noAssociationFields(),
            $metadataResolver ?? $this->noResolvedItems(),
            $productRepository,
            $contentAggregator,
            new ProductParentContentLoader($productRepository, $contentAggregator, $this->createStub(EntityManagerInterface::class)),
            $referenceStore ?? new ReferenceStore(),
            $variantProperties,
        );
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

    /** Every product aggregates to an empty dimension content of its own. */
    protected function emptyContents(): ContentAggregatorInterface
    {
        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willReturnCallback(
            static fn (ProductInterface $product): ProductDimensionContent => new ProductDimensionContent($product),
        );

        return $contentAggregator;
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

    protected function noResolvedItems(): MetadataResolver
    {
        $resolver = $this->createStub(MetadataResolver::class);
        $resolver->method('resolveItems')->willReturn([]);

        return $resolver;
    }

    protected function noVariants(): ProductRepositoryInterface
    {
        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('findBy')->willReturn([]);

        return $repository;
    }
}
