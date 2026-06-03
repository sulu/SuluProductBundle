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

namespace Sulu\Product\Tests\Unit\Application\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Application\Mapper\ProductDetailsMapper;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductTranslation;

#[CoversClass(ProductDetailsMapper::class)]
class ProductDetailsMapperTest extends TestCase
{
    use ProphecyTrait;

    private ProductDetailsMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ProductDetailsMapper();
    }

    public function testMapProductDataSetsCode(): void
    {
        $product = new Product();

        $this->mapper->mapProductData($product, ['code' => 'PROD-1']);

        $this->assertSame('PROD-1', $product->getCode());
        $this->assertNull($product->getTranslation('en'));
    }

    public function testMapProductDataResetsCodeWhenMissing(): void
    {
        $product = new Product();
        $product->setCode('PRE-EXISTING');

        $this->mapper->mapProductData($product, []);

        $this->assertNull($product->getCode());
    }

    public function testMapProductDataCreatesTranslationWhenNoneExists(): void
    {
        $product = new Product();

        $this->mapper->mapProductData($product, [
            'code' => 'PROD-2',
            'locale' => 'en',
            'name' => 'Hello',
        ]);

        $translation = $product->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('en', $translation->getLocale());
        $this->assertSame('Hello', $translation->getName());
        $this->assertSame('PROD-2', $product->getCode());
    }

    public function testMapProductDataUpdatesExistingTranslation(): void
    {
        $product = new Product();
        $product->addTranslation(new ProductTranslation($product, 'en', 'Old Name'));

        $this->mapper->mapProductData($product, [
            'locale' => 'en',
            'name' => 'New Name',
        ]);

        $translation = $product->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('New Name', $translation->getName());
    }

    public function testMapProductDataIgnoresTranslationWhenNameMissing(): void
    {
        $product = new Product();

        $this->mapper->mapProductData($product, [
            'locale' => 'en',
        ]);

        $this->assertNull($product->getTranslation('en'));
    }

    public function testMapProductDataIgnoresTranslationWhenLocaleMissing(): void
    {
        $product = new Product();

        $this->mapper->mapProductData($product, [
            'name' => 'Orphan',
        ]);

        // No translation should be created without a locale.
        $this->assertCount(0, \iterator_to_array((function() use ($product) {
            // Iterate over all known locales to make sure none was added; since
            // we cannot enumerate translations directly, probe a small set.
            foreach (['en', 'de', 'fr'] as $locale) {
                if (null !== $product->getTranslation($locale)) {
                    yield $locale;
                }
            }
        })()));
    }

    public function testMapProductDataDefaultsNameToEmptyStringWhenNull(): void
    {
        $product = new Product();

        $this->mapper->mapProductData($product, [
            'locale' => 'en',
            'name' => null,
        ]);

        // 'name' key exists but is null -> existing branch creates new translation
        // with empty string fallback.
        $translation = $product->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('', $translation->getName());
    }
}
