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
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryRepositoryInterface;
use Sulu\Content\Domain\Factory\CategoryFactoryInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Exception\InvalidMainCategoryException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductMainCategoryDataMapper;

#[CoversClass(ProductMainCategoryDataMapper::class)]
class ProductMainCategoryDataMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<CategoryFactoryInterface> */
    private ObjectProphecy $categoryFactory;

    /** @var ObjectProphecy<CategoryRepositoryInterface> */
    private ObjectProphecy $categoryRepository;

    private ProductMainCategoryDataMapper $mapper;

    protected function setUp(): void
    {
        $this->categoryFactory = $this->prophesize(CategoryFactoryInterface::class);
        $this->categoryRepository = $this->prophesize(CategoryRepositoryInterface::class);
        $this->mapper = $this->createMapper(null);
    }

    private function createMapper(?string $rootKey): ProductMainCategoryDataMapper
    {
        return new ProductMainCategoryDataMapper(
            $this->categoryFactory->reveal(),
            $this->categoryRepository->reveal(),
            $rootKey,
        );
    }

    private function createCategory(int $id, int $lft, int $rgt): Category
    {
        $category = new Category();
        $category->setId($id);
        $category->setLft($lft);
        $category->setRgt($rgt);

        return $category;
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

    public function testAcceptsACategoryBelowTheRoot(): void
    {
        $this->categoryRepository->findCategoryByKey('shop')->willReturn($this->createCategory(1, 1, 10));
        $category = $this->createCategory(2, 2, 3);
        $this->categoryFactory->create([2])->willReturn([$category]);
        $localized = new ProductDimensionContent(new Product());

        $this->createMapper('shop')->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => 2]);

        $this->assertSame($category, $localized->getMainCategory());
    }

    public function testRejectsACategoryOutsideTheRoot(): void
    {
        $this->categoryRepository->findCategoryByKey('shop')->willReturn($this->createCategory(1, 1, 10));
        $this->categoryFactory->create([3])->willReturn([$this->createCategory(3, 11, 12)]);
        $localized = new ProductDimensionContent(new Product());

        $this->expectException(InvalidMainCategoryException::class);

        $this->createMapper('shop')->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => 3]);
    }

    public function testRejectsTheRootItself(): void
    {
        $root = $this->createCategory(1, 1, 10);
        $this->categoryRepository->findCategoryByKey('shop')->willReturn($root);
        $this->categoryFactory->create([1])->willReturn([$root]);

        $this->expectException(InvalidMainCategoryException::class);

        $this->createMapper('shop')->map(
            new ProductDimensionContent(new Product()),
            new ProductDimensionContent(new Product()),
            ['mainCategory' => 1],
        );
    }

    public function testRejectsEveryCategoryWhenTheRootDoesNotExist(): void
    {
        $this->categoryRepository->findCategoryByKey('missing')->willReturn(null);
        $this->categoryFactory->create([2])->willReturn([$this->createCategory(2, 2, 3)]);

        $this->expectException(InvalidMainCategoryException::class);

        $this->createMapper('missing')->map(
            new ProductDimensionContent(new Product()),
            new ProductDimensionContent(new Product()),
            ['mainCategory' => 2],
        );
    }

    public function testKeepsAStoredCategoryOutsideTheRoot(): void
    {
        $stored = $this->createCategory(3, 11, 12);
        $this->categoryRepository->findCategoryByKey(\Prophecy\Argument::cetera())->shouldNotBeCalled();
        $this->categoryFactory->create([3])->willReturn([$stored]);
        $localized = new ProductDimensionContent(new Product());
        $localized->setMainCategory($stored);

        $this->createMapper('shop')->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => 3]);

        $this->assertSame($stored, $localized->getMainCategory());
    }

    public function testClearingNeedsNoRootCheck(): void
    {
        $this->categoryRepository->findCategoryByKey(\Prophecy\Argument::cetera())->shouldNotBeCalled();
        $localized = new ProductDimensionContent(new Product());
        $localized->setMainCategory($this->createCategory(3, 11, 12));

        $this->createMapper('shop')->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => null]);

        $this->assertNull($localized->getMainCategory());
    }
}
