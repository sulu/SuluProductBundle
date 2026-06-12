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

namespace Sulu\Product\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttribute;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductTranslation;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Product::class)]
class ProductTest extends TestCase
{
    public function testConstructorGeneratesUuidWhenNoneProvided(): void
    {
        $product = new Product();

        $this->assertTrue(Uuid::isValid($product->getUuid()));
        $this->assertSame($product->getUuid(), $product->getId());
    }

    public function testConstructorAcceptsProvidedUuid(): void
    {
        $uuid = Uuid::v7()->toRfc4122();
        $product = new Product($uuid);

        $this->assertSame($uuid, $product->getUuid());
        $this->assertSame($uuid, $product->getId());
    }

    public function testConstructorInitializesCollectionsAndDefaults(): void
    {
        $product = new Product();

        $this->assertNull($product->getCode());
        $this->assertCount(0, $product->getAttributes());
        $this->assertNull($product->getTranslation('en'));
    }

    public function testSetCodeIsFluentAndStores(): void
    {
        $product = new Product();

        $this->assertSame($product, $product->setCode('SKU-1'));
        $this->assertSame('SKU-1', $product->getCode());

        $product->setCode(null);
        $this->assertNull($product->getCode());
    }

    public function testGetTranslationByExplicitLocale(): void
    {
        $product = new Product();
        $en = new ProductTranslation($product, 'en', 'Product');
        $de = new ProductTranslation($product, 'de', 'Produkt');
        $product->addTranslation($en);
        $product->addTranslation($de);

        $this->assertSame($en, $product->getTranslation('en'));
        $this->assertSame($de, $product->getTranslation('de'));
        $this->assertNull($product->getTranslation('fr'));
    }

    public function testAddTranslationIsFluentAndDeduplicates(): void
    {
        $product = new Product();
        $translation = new ProductTranslation($product, 'en', 'Product');

        $this->assertSame($product, $product->addTranslation($translation));
        $product->addTranslation($translation);

        $this->assertSame($translation, $product->getTranslation('en'));
    }

    public function testRemoveTranslationIsFluent(): void
    {
        $product = new Product();
        $translation = new ProductTranslation($product, 'en', 'Product');
        $product->addTranslation($translation);

        $this->assertSame($product, $product->removeTranslation($translation));
        $this->assertNull($product->getTranslation('en'));
    }

    public function testGetAttributesReturnsCollection(): void
    {
        $product = new Product();
        $attr = new Attribute();
        $productAttribute = new ProductAttribute($product, $attr, 'color');

        $product->addAttribute($productAttribute);

        $this->assertCount(1, $product->getAttributes());
        $this->assertSame($productAttribute, $product->getAttributes()->first());
    }

    public function testAddAttributeIsFluentAndDeduplicates(): void
    {
        $product = new Product();
        $attr = new Attribute();
        $productAttribute = new ProductAttribute($product, $attr, 'color');

        $this->assertSame($product, $product->addAttribute($productAttribute));
        $product->addAttribute($productAttribute);

        $this->assertCount(1, $product->getAttributes());
    }

    public function testRemoveAttributeIsFluent(): void
    {
        $product = new Product();
        $attr = new Attribute();
        $productAttribute = new ProductAttribute($product, $attr, 'color');
        $product->addAttribute($productAttribute);

        $this->assertSame($product, $product->removeAttribute($productAttribute));
        $this->assertCount(0, $product->getAttributes());
    }

    public function testCreateDimensionContentReturnsBoundInstance(): void
    {
        $product = new Product();
        $dimensionContent = $product->createDimensionContent();

        $this->assertInstanceOf(ProductDimensionContent::class, $dimensionContent);
        $this->assertSame($product, $dimensionContent->getResource());
    }
}
