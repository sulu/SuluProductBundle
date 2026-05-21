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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\PropertyResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\SingleProductSelectionPropertyResolver;

#[CoversClass(SingleProductSelectionPropertyResolver::class)]
class SingleProductSelectionPropertyResolverTest extends TestCase
{
    private SingleProductSelectionPropertyResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new SingleProductSelectionPropertyResolver();
    }

    public function testResolveNull(): void
    {
        $contentView = $this->resolver->resolve(null, 'en');
        $this->assertNull($contentView->getContent());
        $this->assertSame(['id' => null], $contentView->getView());
    }

    public function testResolveParams(): void
    {
        $contentView = $this->resolver->resolve(null, 'en', ['custom' => 'params']);
        $this->assertNull($contentView->getContent());
        $this->assertSame(['id' => null, 'custom' => 'params'], $contentView->getView());
    }

    #[DataProvider('provideUnresolvableData')]
    public function testResolveUnresolvableData(mixed $data): void
    {
        $contentView = $this->resolver->resolve($data, 'en');
        $this->assertNull($contentView->getContent());
        $this->assertSame(['id' => null], $contentView->getView());
    }

    public static function provideUnresolvableData(): iterable
    {
        yield 'null' => [null];
        yield 'smart_content' => [['source' => '123']];
        yield 'single_int_value' => [1];
        yield 'object' => [(object) [1, 2]];
        yield 'ids_list' => [['ids' => [1, 2]]];
        yield 'id_list' => [['id' => [1, 2]]];
    }

    #[DataProvider('provideResolvableData')]
    public function testResolveResolvableData(string $data): void
    {
        $contentView = $this->resolver->resolve($data, 'en');
        $content = $contentView->getContent();
        $this->assertInstanceOf(ResolvableResource::class, $content);
        $this->assertSame($data, $content->getId());
        $this->assertSame('product', $content->getResourceLoaderKey());
        $references = $contentView->getReferences();
        $this->assertCount(1, $references);
        $this->assertSame($data, $references[0]->getResourceId());
        $this->assertSame(ProductInterface::RESOURCE_KEY, $references[0]->getResourceKey());
        $this->assertSame(['id' => $data], $contentView->getView());
    }

    public static function provideResolvableData(): iterable
    {
        yield 'string_id' => ['1'];
    }

    public function testCustomResourceLoader(): void
    {
        $contentView = $this->resolver->resolve('1', 'en', ['resourceLoader' => 'custom_product']);
        $content = $contentView->getContent();
        $this->assertInstanceOf(ResolvableResource::class, $content);
        $this->assertSame('1', $content->getId());
        $this->assertSame('custom_product', $content->getResourceLoaderKey());
        $references = $contentView->getReferences();
        $this->assertCount(1, $references);
        $this->assertSame('1', $references[0]->getResourceId());
        $this->assertSame(ProductInterface::RESOURCE_KEY, $references[0]->getResourceKey());
    }

    public function testResolveWithMetadata(): void
    {
        $contentView = $this->resolver->resolve('1', 'en', ['properties' => ['property1' => 'value1', 'property2' => 'value2']]);
        $content = $contentView->getContent();
        $this->assertInstanceOf(ResolvableResource::class, $content);
        $this->assertSame(['properties' => ['property1' => 'value1', 'property2' => 'value2', 'title' => 'title', 'url' => 'url']], $content->getMetadata());
        $references = $contentView->getReferences();
        $this->assertCount(1, $references);
        $this->assertSame('1', $references[0]->getResourceId());
        $this->assertSame(ProductInterface::RESOURCE_KEY, $references[0]->getResourceKey());
    }

    public function testResolveWithoutMetadata(): void
    {
        $contentView = $this->resolver->resolve('1', 'en');
        $content = $contentView->getContent();
        $this->assertInstanceOf(ResolvableResource::class, $content);
        $this->assertSame(['properties' => ['title' => 'title', 'url' => 'url']], $content->getMetadata());
        $references = $contentView->getReferences();
        $this->assertCount(1, $references);
    }

    public function testResolveWithEmptyMetadata(): void
    {
        $metadata = new FieldMetadata('test_field');
        $contentView = $this->resolver->resolve('1', 'en', ['metadata' => $metadata]);
        $content = $contentView->getContent();
        $this->assertInstanceOf(ResolvableResource::class, $content);
        $this->assertSame(['properties' => ['title' => 'title', 'url' => 'url']], $content->getMetadata());
    }
}
