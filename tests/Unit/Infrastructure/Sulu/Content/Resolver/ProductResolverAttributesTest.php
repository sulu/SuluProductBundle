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
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Attribute\AttributeValueConverter;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;
use Sulu\Product\Tests\Unit\Support\ProductAttributeFragmentLoader;

/** Values come as the admin API has them; the formatting itself is the types' own test, grouping the Twig filter's. */
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

    public function testReturnsAMapOfAttributesAndValuesKeyedByAttributeKey(): void
    {
        $content = $this->createContent();
        $this->addTextValue($content, $this->createAttribute('housing'), 'Zink');
        $this->addTextValue($content, $this->createAttribute('weight'), '48 g');

        $attributes = $this->resolveAttributes($content);

        self::assertSame(['housing', 'weight'], \array_keys($attributes));
        self::assertSame('48 g', $attributes['weight']['value']);
        self::assertSame('48 g', $attributes['weight']['formattedValue']);
        self::assertSame('weight', $attributes['weight']['attribute']->getKey());
    }

    public function testAMultiFieldAttributeResolvesToOneObjectOfItsValues(): void
    {
        $content = $this->createContent();
        $range = $this->createAttribute('range');
        $range->setType('range');
        foreach (['from' => 1.5, 'to' => 4.0] as $valueKey => $number) {
            $row = new ProductAttributeValue($content, $range, 'range', valueKey: $valueKey);
            $row->setNumber($number);
            $content->addAttribute($row);
        }
        $attributes = $this->resolveAttributes($content, $this->attributeResolver(['range' => ['from' => 'number', 'to' => 'number']]));

        self::assertSame(['range'], \array_keys($attributes));
        self::assertSame(['from' => 1.5, 'to' => 4.0], $attributes['range']['value']);
        self::assertSame('1.5 – 4', $attributes['range']['formattedValue']);
    }

    public function testFormatsInTheLocaleOfTheContent(): void
    {
        $content = $this->createContent('de');
        $released = $this->createAttribute('released');
        $released->setType(AttributeInterface::TYPE_DATE);
        $row = new ProductAttributeValue($content, $released, 'released');
        $row->setNumber((float) \strtotime('2024-05-01 00:00:00 UTC'));
        $content->addAttribute($row);

        $attributes = $this->resolveAttributes($content);

        self::assertSame('2024-05-01', $attributes['released']['value']);
        self::assertSame('01.05.2024', $attributes['released']['formattedValue']);
    }

    public function testAnAttributeWithoutValueFieldsResolvesToNull(): void
    {
        $content = $this->createContent();
        $unknown = $this->createAttribute('unknown');
        $unknown->setType('unknown');
        $this->addTextValue($content, $unknown, 'x');

        $attributes = $this->resolveAttributes($content);

        self::assertNull($attributes['unknown']['value']);
        self::assertNull($attributes['unknown']['formattedValue']);
    }

    public function testAMultiFieldAttributeWithoutAKnownRowResolvesToNull(): void
    {
        $content = $this->createContent();
        $range = $this->createAttribute('range');
        $range->setType('range');
        $row = new ProductAttributeValue($content, $range, 'range', valueKey: 'middle');
        $row->setNumber(2.0);
        $content->addAttribute($row);

        $attributes = $this->resolveAttributes($content, $this->attributeResolver(['range' => ['from' => 'number', 'to' => 'number']]));

        self::assertNull($attributes['range']['value']);
    }

    public function testKeepsAnAttributeWhoseValueFormatsToNothing(): void
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

        $resolved = $this->resolveContent($parentContent, null, $this->createResolver(currentVariant: $content));

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

        return $this->unwrap($content);
    }

    /**
     * @return array<string, array{attribute: AttributeInterface, value: mixed, formattedValue: mixed}>
     */
    private function resolveAttributes(ProductDimensionContent $content, ?ProductResolver $resolver = null): array
    {
        $view = $this->resolveContent($content, null, $resolver ?? $this->attributeResolver())['attributes'];
        self::assertInstanceOf(ContentView::class, $view);

        $attributes = $view->getContent();
        self::assertIsArray($attributes);

        return $this->unwrap($attributes);
    }

    /**
     * @param array<string, array<string, string>> $fields the field types by field name, by attribute type
     */
    private function attributeResolver(array $fields = []): ProductResolver
    {
        $loader = new ProductAttributeFragmentLoader($fields);

        return $this->createResolver($loader, attributeValueConverter: new AttributeValueConverter($loader));
    }

    /**
     * The attributes as a template gets them, once the content resolver has resolved the content views.
     *
     * @param array<array-key, mixed> $views
     *
     * @return array<string, array{attribute: AttributeInterface, value: mixed, formattedValue: mixed}>
     */
    private function unwrap(array $views): array
    {
        $attributes = [];
        foreach ($views as $key => $view) {
            self::assertInstanceOf(ContentView::class, $view);
            $content = $view->getContent();
            self::assertIsArray($content);
            self::assertInstanceOf(ContentView::class, $content['attribute']);
            self::assertInstanceOf(ContentView::class, $content['value']);
            self::assertInstanceOf(ContentView::class, $content['formattedValue']);

            $attribute = $content['attribute']->getContent();
            self::assertInstanceOf(AttributeInterface::class, $attribute);

            $value = $content['value']->getContent();
            $attributes[(string) $key] = [
                'attribute' => $attribute,
                'value' => \is_array($value) ? \array_map(static fn (mixed $field): mixed => $field instanceof ContentView ? $field->getContent() : $field, $value) : $value,
                'formattedValue' => $content['formattedValue']->getContent(),
            ];
        }

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
