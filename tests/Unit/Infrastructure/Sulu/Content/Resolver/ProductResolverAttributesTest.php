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
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;
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

    /**
     * @return array<string, ProductAttributeValue>
     */
    private function resolveAttributes(ProductDimensionContent $content): array
    {
        $view = $this->resolveContent($content)['attributes'];
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
