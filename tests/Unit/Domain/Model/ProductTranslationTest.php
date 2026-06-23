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
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductTranslation;

#[CoversClass(ProductTranslation::class)]
class ProductTranslationTest extends TestCase
{
    public function testConstructorAssignsValues(): void
    {
        $product = new Product(new ProductFamily());
        $translation = new ProductTranslation($product, 'en', 'Product');

        $this->assertSame($product, $translation->getProduct());
        $this->assertSame('en', $translation->getLocale());
        $this->assertSame('Product', $translation->getName());
    }

    public function testSetLocaleIsFluentAndStores(): void
    {
        $translation = new ProductTranslation(new Product(new ProductFamily()), 'en', 'Product');

        $this->assertSame($translation, $translation->setLocale('de'));
        $this->assertSame('de', $translation->getLocale());
    }

    public function testSetNameIsFluentAndStores(): void
    {
        $translation = new ProductTranslation(new Product(new ProductFamily()), 'en', 'Product');

        $this->assertSame($translation, $translation->setName('Produkt'));
        $this->assertSame('Produkt', $translation->getName());
    }

    public function testGetIdReturnsDoctrineGeneratedId(): void
    {
        $model = new ProductTranslation(new Product(new ProductFamily()), 'en', 'Product');
        $ref = new \ReflectionProperty(ProductTranslation::class, 'id');
        $ref->setValue($model, 42);
        $this->assertSame(42, $model->getId());
    }
}
