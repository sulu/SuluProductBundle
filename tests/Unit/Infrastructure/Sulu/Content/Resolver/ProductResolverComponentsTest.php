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
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\MetadataResolver\MetadataResolver;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductComponent;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;

#[CoversClass(ProductResolver::class)]
final class ProductResolverComponentsTest extends ProductResolverTestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<MetadataResolver> */
    private ObjectProphecy $metadataResolver;

    protected function setUp(): void
    {
        $this->metadataResolver = $this->prophesize(MetadataResolver::class);
    }

    public function testResolvesComponentTargetsInPositionOrder(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());
        $dimensionContent->setLocale('en');
        $dimensionContent->addComponent(new ProductComponent($dimensionContent, new Product('uuid-c'), 1));
        $dimensionContent->addComponent(new ProductComponent($dimensionContent, new Product('uuid-b'), 0));

        $componentsView = ContentView::create(['resolved-components'], []);

        $field = new FieldMetadata('components');
        $field->setType('product_selection');

        $this->metadataResolver->resolveItems(['components' => $field], ['components' => ['uuid-b', 'uuid-c']], 'en')
            ->willReturn(['components' => $componentsView])
            ->shouldBeCalledOnce();

        $content = $this->resolveContent($dimensionContent, ['components' => 'product.components'], $this->resolver());

        self::assertSame($componentsView, $content['components']);
    }

    public function testResolvesComponentsUnderTheRequestedOutputKey(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());
        $dimensionContent->setLocale('en');

        $componentsView = ContentView::create([], []);

        $field = new FieldMetadata('components');
        $field->setType('product_selection');

        $this->metadataResolver->resolveItems(['components' => $field], ['components' => []], 'en')
            ->willReturn(['components' => $componentsView]);

        $content = $this->resolveContent($dimensionContent, ['parts' => 'product.components'], $this->resolver());

        self::assertSame($componentsView, $content['parts']);
        self::assertArrayNotHasKey('components', $content);
    }

    public function testOmitsComponentsForAReferenceThatDoesNotAskForThem(): void
    {
        $this->metadataResolver->resolveItems()->shouldNotBeCalled();

        $dimensionContent = new ProductDimensionContent(new Product());
        $dimensionContent->setLocale('en');

        $content = $this->resolveContent($dimensionContent, ['title' => 'title'], $this->resolver());

        self::assertArrayNotHasKey('components', $content);
    }

    private function resolver(): ProductResolver
    {
        return $this->createResolver(
            metadataResolver: $this->metadataResolver->reveal(),
        );
    }
}
