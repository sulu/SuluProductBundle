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
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\SingleProductFamilySelectionPropertyResolver;

#[CoversClass(SingleProductFamilySelectionPropertyResolver::class)]
class SingleProductFamilySelectionPropertyResolverTest extends TestCase
{
    private SingleProductFamilySelectionPropertyResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new SingleProductFamilySelectionPropertyResolver();
    }

    public function testGetType(): void
    {
        $this->assertSame('single_product_family_selection', SingleProductFamilySelectionPropertyResolver::getType());
    }

    #[DataProvider('provideUnresolvableData')]
    public function testResolveUnresolvableData(mixed $data): void
    {
        $contentView = $this->resolver->resolve($data, 'en', ['custom' => 'params']);

        $this->assertNull($contentView->getContent());
        $this->assertSame(['id' => null, 'custom' => 'params'], $contentView->getView());
    }

    /** @return iterable<string, array{mixed}> */
    public static function provideUnresolvableData(): iterable
    {
        yield 'null' => [null];
        yield 'int' => [1];
        yield 'list' => [['uuid-1']];
    }

    public function testResolveResolvableData(): void
    {
        $contentView = $this->resolver->resolve('uuid-1', 'en');

        $content = $contentView->getContent();
        $this->assertInstanceOf(ResolvableResource::class, $content);
        $this->assertSame('uuid-1', $content->getId());
        $this->assertSame('product_family', $content->getResourceLoaderKey());

        $references = $contentView->getReferences();
        $this->assertCount(1, $references);
        $this->assertSame('uuid-1', $references[0]->getResourceId());
        $this->assertSame(ProductFamilyInterface::RESOURCE_KEY, $references[0]->getResourceKey());

        $this->assertSame(['id' => 'uuid-1'], $contentView->getView());
    }

    public function testCustomResourceLoader(): void
    {
        $content = $this->resolver->resolve('uuid-1', 'en', ['resourceLoader' => 'custom_family'])->getContent();

        $this->assertInstanceOf(ResolvableResource::class, $content);
        $this->assertSame('custom_family', $content->getResourceLoaderKey());
    }
}
