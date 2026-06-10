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
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttribute;
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

        $dimensionContent = $this->prophesize(\Sulu\Product\Domain\Model\ProductDimensionContentInterface::class);

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent->reveal());
        $this->contentResolver->resolve($dimensionContent->reveal(), ['title' => 'title'])
            ->willReturn(['title' => 'Hello']);
        $this->referenceStore->add('uuid-1', ProductInterface::RESOURCE_KEY)->shouldBeCalled();

        $result = $this->extension->loadProduct('uuid-1', ['title' => 'title'], 'en');

        $this->assertIsArray($result);
        /** @var array{title: string, attributes: list<array{key: string, label: string, type: string, value: mixed}>} $result */
        $this->assertSame('Hello', $result['title']);
        $this->assertSame([], $result['attributes']);
    }

    public function testLoadProductUsesRequestLocaleWhenNotProvided(): void
    {
        $product = new Product('uuid-1');

        $dimensionContent = $this->prophesize(\Sulu\Product\Domain\Model\ProductDimensionContentInterface::class);

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
        /** @var array{attributes: list<array{key: string, label: string, type: string, value: mixed}>} $result */
        $this->assertSame([], $result['attributes']);
    }

    public function testLoadProductFormatsAttributes(): void
    {
        $product = new Product('uuid-1');

        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $translation = new AttributeTranslation($attribute, 'en', 'Color');
        $attribute->addTranslation($translation);

        $productAttribute = new ProductAttribute($product, $attribute, 'color');
        $productAttribute->setText('Red');

        $product->addAttribute($productAttribute);

        $dimensionContent = $this->prophesize(\Sulu\Product\Domain\Model\ProductDimensionContentInterface::class);

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent->reveal());
        $this->contentResolver->resolve($dimensionContent->reveal(), [])->willReturn([]);
        $this->referenceStore->add('uuid-1', ProductInterface::RESOURCE_KEY)->shouldBeCalled();

        $result = $this->extension->loadProduct('uuid-1', [], 'en');

        $this->assertIsArray($result);
        /** @var array{attributes: list<array{key: string, label: string, type: string, value: mixed}>} $result */
        $this->assertCount(1, $result['attributes']);
        $this->assertSame('color', $result['attributes'][0]['key']);
        $this->assertSame('Color', $result['attributes'][0]['label']);
        $this->assertSame(AttributeInterface::TYPE_TEXT, $result['attributes'][0]['type']);
        $this->assertSame('Red', $result['attributes'][0]['value']);
    }

    public function testLoadProductReturnsNullWhenNoLocalization(): void
    {
        $this->requestAnalyzer->getCurrentLocalization()->willReturn(null);

        $result = $this->extension->loadProduct('uuid-1', []);

        $this->assertNull($result);
    }

    public function testLoadProductFormatsOptionsAttribute(): void
    {
        $product = new Product('uuid-1');

        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $attrTranslation = new AttributeTranslation($attribute, 'en', 'Color');
        $attribute->addTranslation($attrTranslation);

        $option = new AttributeOption($attribute, 'red');
        $optionTranslation = new AttributeOptionTranslation($option, 'en', 'Red');
        $option->addTranslation($optionTranslation);

        $productAttribute = new ProductAttribute($product, $attribute, 'color');
        $productAttribute->setAttributeOptionKey('red');
        $productAttribute->setAttributeOption($option);

        $product->addAttribute($productAttribute);

        $dimensionContent = $this->prophesize(\Sulu\Product\Domain\Model\ProductDimensionContentInterface::class);

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent->reveal());
        $this->contentResolver->resolve($dimensionContent->reveal(), [])->willReturn([]);
        $this->referenceStore->add('uuid-1', ProductInterface::RESOURCE_KEY)->shouldBeCalled();

        $result = $this->extension->loadProduct('uuid-1', [], 'en');

        $this->assertIsArray($result);
        /** @var array{attributes: list<array{key: string, label: string, type: string, value: mixed}>} $result */
        $this->assertCount(1, $result['attributes']);
        $this->assertSame(AttributeInterface::TYPE_OPTIONS, $result['attributes'][0]['type']);
        $this->assertSame('Red', $result['attributes'][0]['value']);
    }

    public function testLoadProductFormatsNumberAttribute(): void
    {
        $product = new Product('uuid-1');

        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey('weight');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);

        $productAttribute = new ProductAttribute($product, $attribute, 'weight');
        $productAttribute->setNumber(42.5);

        $product->addAttribute($productAttribute);

        $dimensionContent = $this->prophesize(\Sulu\Product\Domain\Model\ProductDimensionContentInterface::class);

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent->reveal());
        $this->contentResolver->resolve($dimensionContent->reveal(), [])->willReturn([]);
        $this->referenceStore->add('uuid-1', ProductInterface::RESOURCE_KEY)->shouldBeCalled();

        $result = $this->extension->loadProduct('uuid-1', [], 'en');

        $this->assertIsArray($result);
        /** @var array{attributes: list<array{key: string, label: string, type: string, value: mixed}>} $result */
        $this->assertCount(1, $result['attributes']);
        $this->assertSame(AttributeInterface::TYPE_NUMBER, $result['attributes'][0]['type']);
        $this->assertSame(42.5, $result['attributes'][0]['value']);
    }

    public function testLoadProductFormatsJsonAttribute(): void
    {
        $product = new Product('uuid-1');

        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey('meta');
        $attribute->setType(AttributeInterface::TYPE_JSON);

        $productAttribute = new ProductAttribute($product, $attribute, 'meta');
        $productAttribute->setJson(['foo' => 'bar']);

        $product->addAttribute($productAttribute);

        $dimensionContent = $this->prophesize(\Sulu\Product\Domain\Model\ProductDimensionContentInterface::class);

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent->reveal());
        $this->contentResolver->resolve($dimensionContent->reveal(), [])->willReturn([]);
        $this->referenceStore->add('uuid-1', ProductInterface::RESOURCE_KEY)->shouldBeCalled();

        $result = $this->extension->loadProduct('uuid-1', [], 'en');

        $this->assertIsArray($result);
        /** @var array{attributes: list<array{key: string, label: string, type: string, value: mixed}>} $result */
        $this->assertCount(1, $result['attributes']);
        $this->assertSame(AttributeInterface::TYPE_JSON, $result['attributes'][0]['type']);
        $this->assertSame(['foo' => 'bar'], $result['attributes'][0]['value']);
    }

    public function testLoadProductFormatsDefaultAttribute(): void
    {
        $product = new Product('uuid-1');

        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey('custom');
        $attribute->setType('unknown_type');

        $productAttribute = new ProductAttribute($product, $attribute, 'custom');
        $productAttribute->setText('some-value');

        $product->addAttribute($productAttribute);

        $dimensionContent = $this->prophesize(\Sulu\Product\Domain\Model\ProductDimensionContentInterface::class);

        $this->productRepository->findOneBy(Argument::cetera())->willReturn($product);
        $this->contentAggregator->aggregate($product, Argument::type('array'))
            ->willReturn($dimensionContent->reveal());
        $this->contentResolver->resolve($dimensionContent->reveal(), [])->willReturn([]);
        $this->referenceStore->add('uuid-1', ProductInterface::RESOURCE_KEY)->shouldBeCalled();

        $result = $this->extension->loadProduct('uuid-1', [], 'en');

        $this->assertIsArray($result);
        /** @var array{attributes: list<array{key: string, label: string, type: string, value: mixed}>} $result */
        $this->assertCount(1, $result['attributes']);
        $this->assertSame('unknown_type', $result['attributes'][0]['type']);
        // getValue() returns text when no option key or number
        $this->assertSame('some-value', $result['attributes'][0]['value']);
    }
}
