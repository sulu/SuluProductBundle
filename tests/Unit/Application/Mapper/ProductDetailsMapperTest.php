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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Mapper\ProductDetailsMapper;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductTranslation;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;

#[CoversClass(ProductDetailsMapper::class)]
class ProductDetailsMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;

    private ProductDetailsMapper $mapper;

    protected function setUp(): void
    {
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->mapper = new ProductDetailsMapper($this->productFamilyRepository->reveal());
    }

    public function testMapProductDataSetsCode(): void
    {
        $product = new Product(new ProductFamily());

        $this->mapper->mapProductData($product, ['code' => 'PROD-1']);

        $this->assertSame('PROD-1', $product->getCode());
        $this->assertNull($product->getTranslation('en'));
    }

    public function testMapProductDataResetsCodeWhenMissing(): void
    {
        $product = new Product(new ProductFamily());
        $product->setCode('PRE-EXISTING');

        $this->mapper->mapProductData($product, []);

        $this->assertNull($product->getCode());
    }

    public function testMapProductDataCreatesTranslationWhenNoneExists(): void
    {
        $product = new Product(new ProductFamily());

        $this->mapper->mapProductData($product, [
            'code' => 'PROD-2',
            'locale' => 'en',
            'title' => 'Hello',
        ]);

        $translation = $product->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('en', $translation->getLocale());
        $this->assertSame('Hello', $translation->getName());
        $this->assertSame('PROD-2', $product->getCode());
    }

    public function testMapProductDataUpdatesExistingTranslation(): void
    {
        $product = new Product(new ProductFamily());
        $product->addTranslation(new ProductTranslation($product, 'en', 'Old Name'));

        $this->mapper->mapProductData($product, [
            'locale' => 'en',
            'title' => 'New Name',
        ]);

        $translation = $product->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('New Name', $translation->getName());
    }

    public function testMapProductDataIgnoresTranslationWhenTitleMissing(): void
    {
        $product = new Product(new ProductFamily());

        $this->mapper->mapProductData($product, [
            'locale' => 'en',
        ]);

        $this->assertNull($product->getTranslation('en'));
    }

    public function testMapProductDataIgnoresTranslationWhenLocaleMissing(): void
    {
        $product = new Product(new ProductFamily());

        $this->mapper->mapProductData($product, [
            'title' => 'Orphan',
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

    public function testMapProductDataDefaultsTitleToEmptyStringWhenNull(): void
    {
        $product = new Product(new ProductFamily());

        $this->mapper->mapProductData($product, [
            'locale' => 'en',
            'title' => null,
        ]);

        // 'title' key exists but is null -> creates new translation with empty string fallback.
        $translation = $product->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('', $translation->getName());
    }

    public function testMapProductDataSetsProductFamilyWhenProvided(): void
    {
        $product = new Product(new ProductFamily());
        $newFamily = new ProductFamily();

        $this->productFamilyRepository->getOneBy(['uuid' => 'family-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn($newFamily);

        $this->mapper->mapProductData($product, [
            'productFamily' => 'family-uuid',
        ]);

        $this->assertSame($newFamily, $product->getProductFamily());
    }

    public function testMapProductDataKeepsProductFamilyWhenMissing(): void
    {
        $family = new ProductFamily();
        $product = new Product($family);

        $this->productFamilyRepository->getOneBy(Argument::any())
            ->shouldNotBeCalled();

        $this->mapper->mapProductData($product, []);

        $this->assertSame($family, $product->getProductFamily());
    }
}
