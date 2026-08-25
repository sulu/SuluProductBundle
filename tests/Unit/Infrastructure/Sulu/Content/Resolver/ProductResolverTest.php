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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Application\ContentResolver\Resolver\ResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;

#[CoversClass(ProductResolver::class)]
class ProductResolverTest extends TestCase
{
    public function testMergesDetailFieldsAndNestsTheThreeSiblings(): void
    {
        $content = $this->createStub(DimensionContentInterface::class);

        $resolver = new ProductResolver(
            $this->resolverReturning(ContentView::create([
                'code' => ContentView::create('NL4FX', []),
                'status' => ContentView::create('published', []),
            ], [])),
            $this->resolverReturning(ContentView::create(['weight' => ['key' => 'weight']], [])),
            $this->resolverReturning(ContentView::create(['accessories' => ContentView::create([], [])], [])),
            $this->resolverReturning(ContentView::create(['a-uuid'], [])),
        );

        $contentView = $resolver->resolve($content);
        self::assertNotNull($contentView);

        /** @var array<string, mixed> $result */
        $result = $contentView->getContent();

        self::assertSame(
            ['code', 'status', 'attributes', 'associations', 'variants'],
            \array_keys($result),
        );
    }

    public function testOmitsChildrenThatResolveToNull(): void
    {
        $content = $this->createStub(DimensionContentInterface::class);

        $resolver = new ProductResolver(
            $this->resolverReturning(ContentView::create(['code' => ContentView::create('NL4FX', [])], [])),
            $this->resolverReturning(null),
            $this->resolverReturning(null),
            $this->resolverReturning(null),
        );

        $contentView = $resolver->resolve($content);
        self::assertNotNull($contentView);

        /** @var array<string, mixed> $result */
        $result = $contentView->getContent();

        self::assertSame(['code'], \array_keys($result));
    }

    public function testReturnsNullWhenTheDetailsResolverDoesNotApply(): void
    {
        $content = $this->createStub(DimensionContentInterface::class);

        $resolver = new ProductResolver(
            $this->resolverReturning(null),
            $this->resolverReturning(null),
            $this->resolverReturning(null),
            $this->resolverReturning(null),
        );

        self::assertNull($resolver->resolve($content));
    }

    private function resolverReturning(?ContentView $contentView): ResolverInterface
    {
        $resolver = $this->createStub(ResolverInterface::class);
        $resolver->method('resolve')->willReturn($contentView);

        return $resolver;
    }
}
