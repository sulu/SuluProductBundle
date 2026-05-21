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
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\ProductSelectionPropertyResolver;

#[CoversClass(ProductSelectionPropertyResolver::class)]
class ProductSelectionPropertyResolverTest extends TestCase
{
    private ProductSelectionPropertyResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new ProductSelectionPropertyResolver();
    }

    public function testResolveEmpty(): void
    {
        $contentView = $this->resolver->resolve([], 'en');
        $this->assertEmpty($contentView->getContent());
        $this->assertSame(['ids' => []], $contentView->getView());
    }

    public function testResolveParams(): void
    {
        $contentView = $this->resolver->resolve([], 'en', ['custom' => 'params']);
        $this->assertEmpty($contentView->getContent());
        $this->assertSame(['ids' => [], 'custom' => 'params'], $contentView->getView());
    }

    #[DataProvider('provideUnresolvableData')]
    public function testResolveUnresolvableData(mixed $data): void
    {
        $contentView = $this->resolver->resolve($data, 'en');
        $this->assertEmpty($contentView->getContent());
        $this->assertSame(['ids' => []], $contentView->getView());
    }

    public static function provideUnresolvableData(): iterable
    {
        yield 'null' => [null];
        yield 'smart_content' => [['source' => '123']];
        yield 'single_value' => [1];
        yield 'object' => [(object) [1, 2]];
        yield 'ids_null' => [['ids' => null]];
        yield 'ids_list' => [['ids' => [1, 2]]];
        yield 'id_list' => [['id' => [1, 2]]];
    }

    #[DataProvider('provideResolvableData')]
    public function testResolveResolvableData(array $data): void
    {
        $contentView = $this->resolver->resolve($data, 'en');
        $content = $contentView->getContent();
        $this->assertIsArray($content);
        foreach ($data as $key => $value) {
            $resolvable = $content[$key] ?? null;
            $this->assertInstanceOf(ResolvableResource::class, $resolvable);
            $this->assertSame($value, $resolvable->getId());
            $this->assertSame('product', $resolvable->getResourceLoaderKey());
        }
        $references = $contentView->getReferences();
        $this->assertCount(\count($data), $references);
        foreach ($data as $key => $value) {
            $reference = $references[$key] ?? null;
            $this->assertNotNull($reference);
            $this->assertSame($value, $reference->getResourceId());
            $this->assertSame(ProductInterface::RESOURCE_KEY, $reference->getResourceKey());
        }
        $this->assertSame(['ids' => $data], $contentView->getView());
    }

    public static function provideResolvableData(): iterable
    {
        yield 'empty' => [[]];
        yield 'string_id' => [['1', '2', '3']];
    }

    public function testCustomResourceLoader(): void
    {
        $contentView = $this->resolver->resolve(['1'], 'en', ['resourceLoader' => 'custom_product']);
        $content = $contentView->getContent();
        $this->assertInstanceOf(ResolvableResource::class, $content[0]);
        $this->assertSame('1', $content[0]->getId());
        $this->assertSame('custom_product', $content[0]->getResourceLoaderKey());
        $references = $contentView->getReferences();
        $this->assertCount(1, $references);
        $this->assertSame('1', $references[0]->getResourceId());
        $this->assertSame(ProductInterface::RESOURCE_KEY, $references[0]->getResourceKey());
    }

    public function testResolveWithMetadata(): void
    {
        $contentView = $this->resolver->resolve(['1'], 'en', ['properties' => ['property1' => 'value1', 'property2' => 'value2']]);
        $content = $contentView->getContent();
        $this->assertIsArray($content);
        $this->assertInstanceOf(ResolvableResource::class, $content[0]);
        $this->assertSame(['properties' => ['property1' => 'value1', 'property2' => 'value2', 'title' => 'title', 'url' => 'url']], $content[0]->getMetadata());
        $references = $contentView->getReferences();
        $this->assertCount(1, $references);
        $this->assertSame('1', $references[0]->getResourceId());
        $this->assertSame(ProductInterface::RESOURCE_KEY, $references[0]->getResourceKey());
    }

    public function testResolveWithoutMetadata(): void
    {
        $contentView = $this->resolver->resolve(['1'], 'en');
        $content = $contentView->getContent();
        $this->assertIsArray($content);
        $this->assertInstanceOf(ResolvableResource::class, $content[0]);
        $this->assertSame(['properties' => ['title' => 'title', 'url' => 'url']], $content[0]->getMetadata());
        $references = $contentView->getReferences();
        $this->assertCount(1, $references);
    }

    public function testResolveWithEmptyMetadata(): void
    {
        $metadata = new FieldMetadata('test_field');
        $contentView = $this->resolver->resolve(['1'], 'en', ['metadata' => $metadata]);
        $content = $contentView->getContent();
        $this->assertIsArray($content);
        $this->assertInstanceOf(ResolvableResource::class, $content[0]);
        $this->assertSame(['properties' => ['title' => 'title', 'url' => 'url']], $content[0]->getMetadata());
    }
}
