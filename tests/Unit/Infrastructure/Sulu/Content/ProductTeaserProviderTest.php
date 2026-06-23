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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Teaser\Configuration\TeaserConfiguration;
use Sulu\Bundle\AdminBundle\Teaser\TeaserTagPropertyExtractor;
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentEnhancer\ContentEnhancerInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductTeaserProvider;
use Sulu\Route\Domain\Model\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ProductTeaserProvider::class)]
class ProductTeaserProviderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ContentAggregatorInterface> */
    private ObjectProphecy $contentAggregator;

    /** @var ObjectProphecy<ContentEnhancerInterface> */
    private ObjectProphecy $contentEnhancer;

    /** @var ObjectProphecy<TranslatorInterface> */
    private ObjectProphecy $translator;

    /** @var ObjectProphecy<TeaserTagPropertyExtractor> */
    private ObjectProphecy $teaserTagPropertyExtractor;

    private ProductTeaserProvider $provider;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentAggregator = $this->prophesize(ContentAggregatorInterface::class);
        $this->contentEnhancer = $this->prophesize(ContentEnhancerInterface::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->translator->trans(Argument::cetera())->willReturnArgument(0);
        $this->teaserTagPropertyExtractor = $this->prophesize(TeaserTagPropertyExtractor::class);

        $this->provider = new ProductTeaserProvider(
            $this->productRepository->reveal(),
            $this->contentAggregator->reveal(),
            $this->contentEnhancer->reveal(),
            $this->translator->reveal(),
            $this->teaserTagPropertyExtractor->reveal(),
        );
    }

    public function testGetConfiguration(): void
    {
        $configuration = $this->provider->getConfiguration();

        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(TeaserConfiguration::class, $configuration);
    }

    public function testFindEmptyIds(): void
    {
        $this->assertSame([], $this->provider->find([], 'en'));
    }

    public function testFindReturnsTeaserForProduct(): void
    {
        $product = new Product(new ProductFamily(), 'uuid-1');
        $dimensionContent = $this->createDimensionContent($product);
        $dimensionContent->getRoute()->willReturn($this->makeRoute('/products/test'));
        $dimensionContent->getExcerptTitle()->willReturn('Excerpt Title');
        $dimensionContent->getTitle()->willReturn('Plain Title');
        $dimensionContent->getExcerptDescription()->willReturn('<p>Desc</p>');
        $dimensionContent->getExcerptMore()->willReturn('Read more');
        $dimensionContent->getExcerptImage()->willReturn(['id' => 42]);
        $dimensionContent->getResourceId()->willReturn('uuid-1');
        $dimensionContent->getMainWebspace()->willReturn('main');
        $dimensionContent->getAdditionalWebspaces()->willReturn([]);
        $revealed = $dimensionContent->reveal();

        $this->productRepository->findBy(Argument::cetera())
            ->willReturn((static function() use ($product) {
                yield $product;
            })());

        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($revealed);

        $this->contentEnhancer->enhance($revealed)->willReturn($revealed);

        $teasers = $this->provider->find(['uuid-1'], 'en');

        $this->assertCount(1, $teasers);
        $this->assertSame('uuid-1', $teasers[0]->getId());
        $this->assertSame('Excerpt Title', $teasers[0]->getTitle());
        $this->assertSame('Desc', $teasers[0]->getDescription());
        $this->assertSame('Read more', $teasers[0]->getMoreText());
        $this->assertSame('/products/test', $teasers[0]->getUrl());
        $this->assertSame(42, $teasers[0]->getMediaId());
    }

    public function testFindFallsBackToTitleWhenNoExcerptTitle(): void
    {
        $product = new Product(new ProductFamily(), 'uuid-1');
        $dimensionContent = $this->createDimensionContent($product);
        $dimensionContent->getRoute()->willReturn($this->makeRoute('/p'));
        $dimensionContent->getExcerptTitle()->willReturn(null);
        $dimensionContent->getTitle()->willReturn('Plain Title');
        $dimensionContent->getExcerptDescription()->willReturn(null);
        $dimensionContent->getExcerptMore()->willReturn(null);
        $dimensionContent->getExcerptImage()->willReturn([]);
        $dimensionContent->getResourceId()->willReturn('uuid-1');
        $dimensionContent->getMainWebspace()->willReturn(null);
        $dimensionContent->getAdditionalWebspaces()->willReturn([]);
        $dimensionContent->getTemplateKey()->willReturn(null);
        $dimensionContent->getLocale()->willReturn(null);

        $revealed = $dimensionContent->reveal();

        $this->productRepository->findBy(Argument::cetera())
            ->willReturn((static function() use ($product) {
                yield $product;
            })());

        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($revealed);

        $this->contentEnhancer->enhance($revealed)->willReturn($revealed);

        $teasers = $this->provider->find(['uuid-1'], 'en');

        $this->assertSame('Plain Title', $teasers[0]->getTitle());
        $this->assertSame('', $teasers[0]->getDescription());
        $this->assertNull($teasers[0]->getMediaId());
    }

    public function testFindReturnsEmptyWhenContentNotFound(): void
    {
        $product = new Product(new ProductFamily(), 'uuid-1');

        $this->productRepository->findBy(Argument::cetera())
            ->willReturn((static function() use ($product) {
                yield $product;
            })());

        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willThrow(new ContentNotFoundException($product, []));

        $this->assertSame([], $this->provider->find(['uuid-1'], 'en'));
    }

    public function testFindSkipsProductWithoutRoute(): void
    {
        $product = new Product(new ProductFamily(), 'uuid-1');
        $dimensionContent = $this->createDimensionContent($product);
        $dimensionContent->getRoute()->willReturn(null);
        $revealed = $dimensionContent->reveal();

        $this->productRepository->findBy(Argument::cetera())
            ->willReturn((static function() use ($product) {
                yield $product;
            })());

        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($revealed);

        $this->contentEnhancer->enhance($revealed)->willReturn($revealed);

        $this->assertSame([], $this->provider->find(['uuid-1'], 'en'));
    }

    public function testFindUsesTaggedDescriptionFallback(): void
    {
        $product = new Product(new ProductFamily(), 'uuid-1');
        $dimensionContent = $this->createDimensionContent($product);
        $dimensionContent->getRoute()->willReturn($this->makeRoute('/p'));
        $dimensionContent->getExcerptTitle()->willReturn(null);
        $dimensionContent->getTitle()->willReturn('T');
        $dimensionContent->getExcerptDescription()->willReturn('');
        $dimensionContent->getExcerptMore()->willReturn(null);
        $dimensionContent->getExcerptImage()->willReturn([]);
        $dimensionContent->getResourceId()->willReturn('uuid-1');
        $dimensionContent->getMainWebspace()->willReturn(null);
        $dimensionContent->getAdditionalWebspaces()->willReturn([]);
        $dimensionContent->getTemplateKey()->willReturn('default');
        $dimensionContent->getLocale()->willReturn('en');
        $dimensionContent->getTemplateData()->willReturn(['summary' => '<em>Summary</em>']);
        $revealed = $dimensionContent->reveal();

        $this->productRepository->findBy(Argument::cetera())
            ->willReturn((static function() use ($product) {
                yield $product;
            })());

        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($revealed);

        $this->contentEnhancer->enhance($revealed)->willReturn($revealed);

        $this->teaserTagPropertyExtractor->extractDescription(
            ProductInterface::TEMPLATE_TYPE,
            'default',
            'en',
            ['summary' => '<em>Summary</em>']
        )->willReturn('<em>Summary</em>');

        $this->teaserTagPropertyExtractor->extractMediaId(
            ProductInterface::TEMPLATE_TYPE,
            'default',
            'en',
            ['summary' => '<em>Summary</em>']
        )->willReturn(99);

        $teasers = $this->provider->find(['uuid-1'], 'en');

        $this->assertSame('Summary', $teasers[0]->getDescription());
        $this->assertSame(99, $teasers[0]->getMediaId());
    }

    /**
     * @return ObjectProphecy<ProductDimensionContentInterface>
     */
    private function createDimensionContent(ProductInterface $product): ObjectProphecy
    {
        /** @var ObjectProphecy<ProductDimensionContentInterface> $dimensionContent */
        $dimensionContent = $this->prophesize(ProductDimensionContentInterface::class);

        return $dimensionContent;
    }

    private function makeRoute(string $slug): Route
    {
        return new Route('products', 'uuid-1', 'en', $slug);
    }
}
