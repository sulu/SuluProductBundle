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
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;

/** Values pass through untouched; formatting and grouping are the Twig filter's own test. */
#[CoversClass(ProductResolver::class)]
class ProductResolverAttributesTest extends ProductResolverTestCase
{
    public function testReturnsNullForNonProductContent(): void
    {
        $content = $this->createStub(DimensionContentInterface::class);

        self::assertNull($this->createResolver()->resolve($content));
    }

    public function testOmitsAttributesWhenResolvedAsAReference(): void
    {
        $content = $this->createContent();
        $this->addTextValue($content, $this->createAttribute('weight'), '48 g');

        self::assertArrayNotHasKey('attributes', $this->resolveContent($content, ['title' => 'title']));
    }

    public function testReturnsAFlatMapOfValuesKeyedByAttributeKey(): void
    {
        $content = $this->createContent();
        $this->addTextValue($content, $this->createAttribute('housing'), 'Zink');
        $this->addTextValue($content, $this->createAttribute('weight'), '48 g');

        $attributes = $this->resolveAttributes($content);

        self::assertSame(['housing', 'weight'], \array_keys($attributes));
        self::assertContainsOnlyInstancesOf(ProductAttributeValue::class, $attributes);
        self::assertSame('48 g', $attributes['weight']->getText());
    }

    public function testPassesEveryValueThroughWithoutFormatting(): void
    {
        $content = $this->createContent();
        $this->addTextValue($content, $this->createAttribute('housing'), '');

        self::assertSame(['housing'], \array_keys($this->resolveAttributes($content)));
    }

    /** Each side carries its own values; `sulu_product_merge_attributes` combines them in the template. */
    public function testAVariantPageKeepsTheParentsAndTheVariantsValuesApart(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $parentContent = new ProductDimensionContent($parent);
        $parentContent->setLocale('de');
        $this->addTextValue($parentContent, $this->createAttribute('housing'), 'Zink');

        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);
        $content = new ProductDimensionContent($variant);
        $content->setLocale('de');
        $content->setStage(DimensionContentInterface::STAGE_DRAFT);
        $this->addTextValue($content, $this->createAttribute('colour'), 'black');

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::once())->method('findOneBy')
            ->with(['uuid' => 'parent-uuid', 'locale' => 'de', 'stage' => DimensionContentInterface::STAGE_DRAFT])
            ->willReturn($parent);
        $productRepository->method('findBy')->willReturn([]);

        $resolved = $this->resolveContent($content, null, $this->createResolver(
            productRepository: $productRepository,
            contentAggregator: $this->aggregatorReturning($parentContent),
        ));

        self::assertSame(['housing'], \array_keys($this->resolveAttributesOf($resolved)));

        $currentVariant = $resolved['currentVariant'];
        self::assertInstanceOf(ContentView::class, $currentVariant);
        $currentVariantContent = $currentVariant->getContent();
        self::assertIsArray($currentVariantContent);

        self::assertSame(['colour'], \array_keys($this->resolveAttributesOf($currentVariantContent)));
    }

    public function testAVariantReferenceCarriesItsOwnValuesOnly(): void
    {
        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent(new Product('parent-uuid'));
        $content = new ProductDimensionContent($variant);
        $content->setLocale('de');
        $this->addTextValue($content, $this->createAttribute('colour'), 'black');

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects(self::never())->method('findOneBy');

        $resolved = $this->resolveContent(
            $content,
            ['attributes' => 'product.attributes'],
            $this->createResolver(productRepository: $productRepository),
        );

        self::assertSame(['colour'], \array_keys($this->resolveAttributesOf($resolved)));
    }

    /**
     * @param mixed[] $resolved
     *
     * @return mixed[]
     */
    private function resolveAttributesOf(array $resolved): array
    {
        $view = $resolved['attributes'];
        self::assertInstanceOf(ContentView::class, $view);
        $content = $view->getContent();
        self::assertIsArray($content);

        return $content;
    }

    private function aggregatorReturning(ProductDimensionContent $content): ContentAggregatorInterface
    {
        $contentAggregator = $this->createStub(ContentAggregatorInterface::class);
        $contentAggregator->method('aggregate')->willReturn($content);

        return $contentAggregator;
    }

    /**
     * @return array<string, ProductAttributeValue>
     */
    private function resolveAttributes(ProductDimensionContent $content, ?ProductResolver $resolver = null): array
    {
        $view = $this->resolveContent($content, null, $resolver)['attributes'];
        self::assertInstanceOf(ContentView::class, $view);

        /** @var array<string, ProductAttributeValue> $attributes */
        $attributes = $view->getContent();

        return $attributes;
    }

    private function createContent(string $locale = 'de'): ProductDimensionContent
    {
        $content = new ProductDimensionContent(new Product());
        $content->setLocale($locale);

        return $content;
    }

    private function createAttribute(string $key): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey($key);
        $attribute->setType(AttributeInterface::TYPE_TEXT);

        return $attribute;
    }

    private function addTextValue(ProductDimensionContent $content, Attribute $attribute, string $text): void
    {
        $value = new ProductAttributeValue($content, $attribute, $attribute->getKey());
        $value->setText($text);
        $content->addAttribute($value);
    }
}
