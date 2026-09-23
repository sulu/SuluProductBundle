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
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\ProductFamilySelectionPropertyResolver;

#[CoversClass(ProductFamilySelectionPropertyResolver::class)]
class ProductFamilySelectionPropertyResolverTest extends TestCase
{
    private ProductFamilySelectionPropertyResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new ProductFamilySelectionPropertyResolver();
    }

    public function testGetType(): void
    {
        $this->assertSame('product_family_selection', ProductFamilySelectionPropertyResolver::getType());
    }

    public function testResolveKeepsParamsInTheView(): void
    {
        $contentView = $this->resolver->resolve([], 'en', ['custom' => 'params']);

        $this->assertSame([], $contentView->getContent());
        $this->assertSame(['ids' => [], 'custom' => 'params'], $contentView->getView());
    }

    #[DataProvider('provideUnresolvableData')]
    public function testResolveUnresolvableData(mixed $data): void
    {
        $contentView = $this->resolver->resolve($data, 'en');

        $this->assertSame([], $contentView->getContent());
        $this->assertSame(['ids' => []], $contentView->getView());
        $this->assertSame([], $contentView->getReferences());
    }

    /** @return iterable<string, array{mixed}> */
    public static function provideUnresolvableData(): iterable
    {
        yield 'null' => [null];
        yield 'single_value' => ['uuid-1'];
        yield 'object' => [(object) ['uuid-1']];
        yield 'map' => [['id' => 'uuid-1']];
        yield 'non_string_id' => [['uuid-1', 2]];
    }

    public function testResolveResolvableData(): void
    {
        $contentView = $this->resolver->resolve(['uuid-1', 'uuid-2'], 'en');

        $content = $contentView->getContent();
        $this->assertIsArray($content);
        $this->assertCount(2, $content);
        foreach (['uuid-1', 'uuid-2'] as $key => $uuid) {
            $this->assertInstanceOf(ResolvableResource::class, $content[$key]);
            $this->assertSame($uuid, $content[$key]->getId());
            $this->assertSame('product_family', $content[$key]->getResourceLoaderKey());
        }

        $references = $contentView->getReferences();
        $this->assertCount(2, $references);
        $this->assertSame('uuid-1', $references[0]->getResourceId());
        $this->assertSame(ProductFamilyInterface::RESOURCE_KEY, $references[0]->getResourceKey());

        $this->assertSame(['ids' => ['uuid-1', 'uuid-2']], $contentView->getView());
    }

    public function testCustomResourceLoader(): void
    {
        $contentView = $this->resolver->resolve(['uuid-1'], 'en', ['resourceLoader' => 'custom_family']);

        $content = $contentView->getContent();
        $this->assertIsArray($content);
        $this->assertInstanceOf(ResolvableResource::class, $content[0]);
        $this->assertSame('custom_family', $content[0]->getResourceLoaderKey());
    }
}
