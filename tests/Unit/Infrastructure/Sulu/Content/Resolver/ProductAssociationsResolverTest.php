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
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\ProductSelectionPropertyResolver;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductAssociationsResolver;

#[CoversClass(ProductAssociationsResolver::class)]
final class ProductAssociationsResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductSelectionPropertyResolver> */
    private ObjectProphecy $productSelectionPropertyResolver;

    private ProductAssociationsResolver $resolver;

    protected function setUp(): void
    {
        $this->productSelectionPropertyResolver = $this->prophesize(ProductSelectionPropertyResolver::class);

        $associationTypeRegistry = new ProductAssociationTypeRegistry([
            'alternative' => ['label' => 'Alternative'],
            'suitable' => ['label' => 'Suitable'],
        ]);

        $this->resolver = new ProductAssociationsResolver(
            $associationTypeRegistry,
            $this->productSelectionPropertyResolver->reveal(),
        );
    }

    public function testReturnsNullForNonProductDimensionContent(): void
    {
        $other = $this->prophesize(DimensionContentInterface::class);

        self::assertNull($this->resolver->resolve($other->reveal()));
    }

    public function testDelegatesEachTypeToProductSelectionPropertyResolver(): void
    {
        $target = new Product('uuid-b');

        $dimensionContent = new ProductDimensionContent(new Product());
        $dimensionContent->setLocale('en');
        $dimensionContent->addAssociation(new ProductAssociation($dimensionContent, $target, 'alternative'));

        $alternativeView = ContentView::create(['resolved-alternative'], []);
        $suitableView = ContentView::create([], []);

        $this->productSelectionPropertyResolver->resolve(['uuid-b'], 'en')
            ->willReturn($alternativeView)
            ->shouldBeCalledOnce();
        $this->productSelectionPropertyResolver->resolve([], 'en')
            ->willReturn($suitableView)
            ->shouldBeCalledOnce();

        $result = $this->resolver->resolve($dimensionContent);

        self::assertInstanceOf(ContentView::class, $result);
        $content = $result->getContent();
        self::assertIsArray($content);
        self::assertSame($alternativeView, $content['alternative']);
        self::assertSame($suitableView, $content['suitable']);
    }
}
