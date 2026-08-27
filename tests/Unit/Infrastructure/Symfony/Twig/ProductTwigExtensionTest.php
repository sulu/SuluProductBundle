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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStoreInterface;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Twig\ProductTwigExtension;
use Twig\TwigFunction;

#[CoversClass(ProductTwigExtension::class)]
class ProductTwigExtensionTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ContentAggregatorInterface> */
    private ObjectProphecy $contentAggregator;

    /** @var ObjectProphecy<RequestAnalyzerInterface> */
    private ObjectProphecy $requestAnalyzer;

    /** @var ObjectProphecy<ReferenceStoreInterface> */
    private ObjectProphecy $referenceStore;

    /** @var ObjectProphecy<ContentResolverInterface> */
    private ObjectProphecy $contentResolver;

    private ProductTwigExtension $extension;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentAggregator = $this->prophesize(ContentAggregatorInterface::class);
        $this->requestAnalyzer = $this->prophesize(RequestAnalyzerInterface::class);
        $this->referenceStore = $this->prophesize(ReferenceStoreInterface::class);
        $this->contentResolver = $this->prophesize(ContentResolverInterface::class);

        $this->extension = new ProductTwigExtension(
            $this->productRepository->reveal(),
            $this->contentAggregator->reveal(),
            $this->requestAnalyzer->reveal(),
            $this->referenceStore->reveal(),
            $this->contentResolver->reveal(),
        );
    }

    public function testGetFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(1, $functions);
        /** @var mixed $twigFunction */
        $twigFunction = $functions[0];
        $this->assertInstanceOf(TwigFunction::class, $twigFunction);
        $this->assertSame('sulu_product_load', $functions[0]->getName());
    }

    public function testLoadProductReturnsNullWhenNotFound(): void
    {
        $this->productRepository->findOneBy(Argument::cetera())->willReturn(null);

        $result = $this->extension->loadProduct('uuid-1', [], 'en');

        $this->assertNull($result);
    }

    public function testLoadProductReturnsResolvedContent(): void
    {
        $product = new Product('uuid-1');

        $dimensionContent = $this->prophesize(ProductDimensionContentInterface::class);

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent->reveal());
        $this->contentResolver->resolve($dimensionContent->reveal(), ['title' => 'title'])
            ->willReturn(['title' => 'Hello']);
        $this->referenceStore->add('uuid-1', ProductInterface::RESOURCE_KEY)->shouldBeCalled();

        $result = $this->extension->loadProduct('uuid-1', ['title' => 'title'], 'en');

        $this->assertIsArray($result);
        $this->assertSame('Hello', $result['title']);
        self::assertArrayNotHasKey('attributes', $result);
    }

    public function testLoadProductUsesRequestLocaleWhenNotProvided(): void
    {
        $product = new Product('uuid-1');

        $dimensionContent = $this->prophesize(ProductDimensionContentInterface::class);

        $localization = new Localization();
        $localization->setLanguage('de');
        $this->requestAnalyzer->getCurrentLocalization()->willReturn($localization);

        $this->productRepository->findOneBy(
            Argument::that(fn (array $criteria) => 'de' === $criteria['locale']),
            Argument::any()
        )->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent->reveal());
        $this->contentResolver->resolve($dimensionContent->reveal(), [])
            ->willReturn([]);
        $this->referenceStore->add('uuid-1', ProductInterface::RESOURCE_KEY)->shouldBeCalled();

        $result = $this->extension->loadProduct('uuid-1', []);

        $this->assertIsArray($result);
        self::assertArrayNotHasKey('attributes', $result);
    }

    public function testLoadProductReturnsNullWhenNoLocalization(): void
    {
        $this->requestAnalyzer->getCurrentLocalization()->willReturn(null);

        $result = $this->extension->loadProduct('uuid-1', []);

        $this->assertNull($result);
    }
}
