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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\DataMapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Content\Domain\Factory\CategoryFactoryInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductMainCategoryDataMapper;

#[CoversClass(ProductMainCategoryDataMapper::class)]
class ProductMainCategoryDataMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<CategoryFactoryInterface> */
    private ObjectProphecy $categoryFactory;

    private ProductMainCategoryDataMapper $mapper;

    protected function setUp(): void
    {
        $this->categoryFactory = $this->prophesize(CategoryFactoryInterface::class);
        $this->mapper = new ProductMainCategoryDataMapper($this->categoryFactory->reveal());
    }

    public function testIgnoresOtherDimensionContent(): void
    {
        $this->categoryFactory->create(\Prophecy\Argument::cetera())->shouldNotBeCalled();

        $this->mapper->map(
            new ProductDimensionContent(new Product()),
            $this->prophesize(DimensionContentInterface::class)->reveal(),
            ['mainCategory' => 1],
        );

        $this->addToAssertionCount(1);
    }

    public function testIgnoresMissingKey(): void
    {
        $this->categoryFactory->create(\Prophecy\Argument::cetera())->shouldNotBeCalled();
        $localized = new ProductDimensionContent(new Product());

        $this->mapper->map(new ProductDimensionContent(new Product()), $localized, []);

        $this->assertNull($localized->getMainCategory());
    }

    public function testSetsMainCategoryAndExcerptCategory(): void
    {
        $category = $this->prophesize(CategoryInterface::class)->reveal();
        $this->categoryFactory->create([5])->willReturn([$category]);
        $localized = new ProductDimensionContent(new Product());

        $this->mapper->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => 5]);

        $this->assertSame($category, $localized->getMainCategory());
        $this->assertSame([$category], $localized->getExcerptCategories());
    }

    public function testNullRemovesMainCategoryAndExcerptCategory(): void
    {
        $category = $this->prophesize(CategoryInterface::class)->reveal();
        $localized = new ProductDimensionContent(new Product());
        $localized->setMainCategory($category);

        $this->mapper->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => null]);

        $this->assertNull($localized->getMainCategory());
        $this->assertSame([], $localized->getExcerptCategories());
    }

    public function testUnknownCategoryIdClearsMainCategory(): void
    {
        $this->categoryFactory->create([9])->willReturn([]);
        $localized = new ProductDimensionContent(new Product());

        $this->mapper->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => 9]);

        $this->assertNull($localized->getMainCategory());
    }
}
