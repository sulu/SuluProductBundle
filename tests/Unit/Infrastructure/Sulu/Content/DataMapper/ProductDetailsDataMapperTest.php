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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryRepositoryInterface;
use Sulu\Content\Domain\Factory\CategoryFactoryInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Exception\InvalidMainCategoryException;
use Sulu\Product\Domain\Exception\InvalidProductStatusException;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductDetailsDataMapper;

#[CoversClass(ProductDetailsDataMapper::class)]
class ProductDetailsDataMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<FormMetadataLoaderInterface> */
    private ObjectProphecy $formMetadataLoader;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<CategoryFactoryInterface> */
    private ObjectProphecy $categoryFactory;

    /** @var ObjectProphecy<CategoryRepositoryInterface> */
    private ObjectProphecy $categoryRepository;

    private ProductDetailsDataMapper $mapper;

    /** @var array<int, string> */
    private array $allowedStatuses = ['announced', 'available', 'discontinued'];

    protected function setUp(): void
    {
        $this->formMetadataLoader = $this->prophesize(FormMetadataLoaderInterface::class);
        $this->formMetadataLoader->getMetadata(Argument::cetera())->willReturn(null);
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->productRepository->existBy(Argument::any())->willReturn(false);
        $this->categoryFactory = $this->prophesize(CategoryFactoryInterface::class);
        $this->categoryRepository = $this->prophesize(CategoryRepositoryInterface::class);
        $this->mapper = $this->createMapperWithRootKey(null);
    }

    private function createMapperWithRootKey(?string $rootKey): ProductDetailsDataMapper
    {
        return new ProductDetailsDataMapper(
            $this->formMetadataLoader->reveal(),
            $this->productFamilyRepository->reveal(),
            $this->productRepository->reveal(),
            $this->allowedStatuses,
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

    private function makeDimensionContent(?string $uuid = null): ProductDimensionContent
    {
        return new ProductDimensionContent(new Product($uuid));
    }

    /**
     * @param array<string, array{type: string, multilingual: bool}> $fields
     */
    private function givenFormMetadata(array $fields): void
    {
        $items = [];
        foreach ($fields as $name => $config) {
            $field = new FieldMetadata($name);
            $field->setType($config['type']);
            $field->setMultilingual($config['multilingual']);
            $items[$name] = $field;
        }

        $formMetadata = new FormMetadata();
        $formMetadata->setKey(ProductInterface::FORM_KEY);
        $formMetadata->setItems($items);

        // the same form applies in every locale
        $this->formMetadataLoader->getMetadata(ProductInterface::FORM_KEY, Argument::type('string'), [])
            ->willReturn($formMetadata);
    }

    public function testEarlyReturnWhenNotProductDimensionContent(): void
    {
        $other = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($other->reveal(), $other->reveal(), ['title' => 'X']);

        $this->addToAssertionCount(1);
    }

    public function testSetsTitle(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->mapper->map($unloc, $loc, ['title' => 'Hello']);

        $this->assertSame('Hello', $loc->getTitle());
    }

    public function testSetsTitleToNull(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();
        $loc->setTitle('old');

        $this->mapper->map($unloc, $loc, ['title' => null]);

        $this->assertNull($loc->getTitle());
    }

    public function testSetsCode(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->mapper->map($unloc, $loc, ['code' => 'SKU-001']);

        $this->assertSame('SKU-001', $unloc->getCode());
    }

    public function testSetsExternalIdentifier(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->mapper->map($unloc, $loc, ['externalIdentifier' => 'EXT-42']);

        $this->assertSame('EXT-42', $unloc->getExternalIdentifier());
    }

    public function testSetsProductFamily(): void
    {
        $family = new ProductFamily('fam-uuid');

        /** @var ObjectProphecy<ProductFamilyInterface> $familyMock */
        $familyMock = $this->prophesize(ProductFamilyInterface::class);
        $this->productFamilyRepository->getOneBy(['uuid' => 'fam-uuid'])
            ->willReturn($familyMock->reveal());

        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->mapper->map($unloc, $loc, ['productFamily' => 'fam-uuid']);

        $this->assertSame($familyMock->reveal(), $unloc->getProductFamily());
    }

    public function testSkipsProductFamilyWhenNull(): void
    {
        $this->productFamilyRepository->getOneBy()->shouldNotBeCalled();

        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();
        $unloc->setCode('SKU');

        $this->mapper->map($unloc, $loc, ['productFamily' => null]);

        $this->assertSame('SKU', $unloc->getCode());
    }

    public function testNoOpWhenNoKnownKeys(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->mapper->map($unloc, $loc, ['locale' => 'en', 'template' => 'product']);

        $this->assertNull($loc->getTitle());
        $this->assertNull($unloc->getCode());
    }

    public function testTitleSkippedWhenLocalizedIsNotProductDimensionContent(): void
    {
        $unloc = $this->makeDimensionContent();
        $locOther = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($unloc, $locOther->reveal(), ['title' => 'X', 'code' => 'SKU-2']);

        $this->assertSame('SKU-2', $unloc->getCode());
    }

    public function testThrowsWhenCodeIsNotUnique(): void
    {
        $this->productRepository->existBy(['code' => 'SKU-TAKEN', 'excludeUuid' => 'product-1'])->willReturn(true);

        $unloc = $this->makeDimensionContent('product-1');
        $loc = $this->makeDimensionContent('product-1');

        $this->expectException(ProductCodeNotUniqueException::class);

        $this->mapper->map($unloc, $loc, ['code' => 'SKU-TAKEN']);
    }

    public function testAllowsCodeOwnedBySameProduct(): void
    {
        $this->productRepository->existBy(['code' => 'SKU-OWN', 'excludeUuid' => 'product-1'])->willReturn(false);

        $unloc = $this->makeDimensionContent('product-1');
        $loc = $this->makeDimensionContent('product-1');

        $this->mapper->map($unloc, $loc, ['code' => 'SKU-OWN']);

        $this->assertSame('SKU-OWN', $unloc->getCode());
    }

    public function testMapsValidStatusOntoUnlocalizedRow(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->mapper->map($unloc, $loc, ['status' => 'available']);

        $this->assertSame('available', $unloc->getStatus());
    }

    public function testThrowsOnUnknownStatus(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        $this->expectException(InvalidProductStatusException::class);

        $this->mapper->map($unloc, $loc, ['status' => 'sold_out']);
    }

    public function testSplitsDetailsBucketByMultilingualAndStoresVerbatim(): void
    {
        $this->givenFormMetadata([
            'details/shortDescription' => ['type' => 'text_editor', 'multilingual' => true],
            'details/image' => ['type' => 'single_media_selection', 'multilingual' => false],
            'details/documents' => ['type' => 'media_selection', 'multilingual' => false],
        ]);

        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();
        $loc->setLocale('en');

        $this->mapper->map($unloc, $loc, [
            'details' => [
                'shortDescription' => '<p>Hello</p>',
                'image' => ['id' => 5],
                'documents' => ['ids' => [1, 2]],
            ],
        ]);

        // each row owns only its half — a multilingual="false" field must not be
        // copied onto the localized row, or it would shadow the shared value per locale
        $this->assertSame(['shortDescription' => '<p>Hello</p>'], $loc->getDetailsData());

        // {"id": 5} stays {"id": 5} — no coercion to a bare int
        $this->assertSame(
            ['image' => ['id' => 5], 'documents' => ['ids' => [1, 2]]],
            $unloc->getDetailsData(),
        );
    }

    public function testMapsUnknownProjectFieldUntouched(): void
    {
        $this->givenFormMetadata([
            'details/datasheet' => ['type' => 'single_media_selection', 'multilingual' => true],
        ]);

        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();
        $loc->setLocale('en');

        $this->mapper->map($unloc, $loc, [
            'details' => ['datasheet' => ['id' => 9]],
        ]);

        $this->assertSame(['datasheet' => ['id' => 9]], $loc->getDetailsData());
    }

    public function testIgnoresSubmittedKeysWithoutAFormProperty(): void
    {
        $this->givenFormMetadata([
            'details/shortDescription' => ['type' => 'text_editor', 'multilingual' => true],
        ]);

        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();
        $loc->setLocale('en');

        $this->mapper->map($unloc, $loc, [
            'details' => ['shortDescription' => '<p>hi</p>', 'bogus' => 'x'],
        ]);

        $this->assertSame(['shortDescription' => '<p>hi</p>'], $loc->getDetailsData());
        $this->assertSame([], $unloc->getDetailsData());
    }

    public function testSharedFieldStaysSharedAcrossLocales(): void
    {
        $this->givenFormMetadata([
            'details/image' => ['type' => 'single_media_selection', 'multilingual' => false],
        ]);

        $unloc = $this->makeDimensionContent();

        $en = $this->makeDimensionContent();
        $en->setLocale('en');
        $this->mapper->map($unloc, $en, ['details' => ['image' => ['id' => 5]]]);

        $de = $this->makeDimensionContent();
        $de->setLocale('de');
        $this->mapper->map($unloc, $de, ['details' => ['image' => ['id' => 9]]]);

        // regression: a multilingual="false" field lives only on the unlocalized row,
        // so changing it in one locale is visible in every other locale
        $this->assertSame(['image' => ['id' => 9]], $unloc->getDetailsData());
        $this->assertSame([], $en->getDetailsData());
        $this->assertSame([], $de->getDetailsData());
    }

    public function testSkipsDetailsWhenLocalizedIsNotProductDimensionContent(): void
    {
        $unloc = $this->makeDimensionContent();
        $other = $this->prophesize(DimensionContentInterface::class);

        $this->mapper->map($unloc, $other->reveal(), ['details' => ['shortDescription' => '<p>hi</p>']]);

        $this->assertSame([], $unloc->getDetailsData());
    }

    public function testSkipsDetailsWhenLocaleIsNull(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();

        // no locale — there is no form to resolve the routing against
        $this->mapper->map($unloc, $loc, ['details' => ['shortDescription' => '<p>hi</p>']]);

        $this->assertSame([], $loc->getDetailsData());
        $this->assertSame([], $unloc->getDetailsData());
    }

    public function testSkipsDetailsWhenFormMetadataIsMissing(): void
    {
        // no givenFormMetadata() — the loader returns null for an unknown form
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();
        $loc->setLocale('en');

        $this->mapper->map($unloc, $loc, ['details' => ['shortDescription' => '<p>hi</p>']]);

        $this->assertSame([], $loc->getDetailsData());
    }

    public function testIgnoresFormPropertiesOutsideTheDetailsBucket(): void
    {
        $this->givenFormMetadata([
            'title' => ['type' => 'text_line', 'multilingual' => true],
            'details' => ['type' => 'text_line', 'multilingual' => true],
            'details/shortDescription' => ['type' => 'text_editor', 'multilingual' => true],
        ]);

        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();
        $loc->setLocale('en');

        $this->mapper->map($unloc, $loc, [
            'details' => ['shortDescription' => '<p>hi</p>'],
        ]);

        // a bare `title`, and a bare `details` with no field after the slash, are not bucket fields
        $this->assertSame(['shortDescription' => '<p>hi</p>'], $loc->getDetailsData());
    }

    public function testSkipsFormFieldsNotSubmitted(): void
    {
        $this->givenFormMetadata([
            'details/shortDescription' => ['type' => 'text_editor', 'multilingual' => true],
            'details/image' => ['type' => 'single_media_selection', 'multilingual' => false],
        ]);

        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();
        $loc->setLocale('en');

        // only one of the two declared fields is submitted
        $this->mapper->map($unloc, $loc, [
            'details' => ['shortDescription' => '<p>hi</p>'],
        ]);

        $this->assertSame(['shortDescription' => '<p>hi</p>'], $loc->getDetailsData());
        $this->assertSame([], $unloc->getDetailsData());
    }

    public function testNoOpWhenDetailsKeyAbsent(): void
    {
        $unloc = $this->makeDimensionContent();
        $loc = $this->makeDimensionContent();
        $loc->setLocale('en');
        $loc->setDetailsData(['shortDescription' => '<p>keep</p>']);

        $this->mapper->map($unloc, $loc, ['title' => 'X']);

        $this->assertSame(['shortDescription' => '<p>keep</p>'], $loc->getDetailsData());
    }

    public function testMainCategoryIgnoresOtherDimensionContent(): void
    {
        $this->categoryFactory->create(Argument::cetera())->shouldNotBeCalled();

        $this->mapper->map(
            new ProductDimensionContent(new Product()),
            $this->prophesize(DimensionContentInterface::class)->reveal(),
            ['mainCategory' => 1],
        );

        $this->addToAssertionCount(1);
    }

    public function testMainCategoryIgnoresMissingKey(): void
    {
        $this->categoryFactory->create(Argument::cetera())->shouldNotBeCalled();
        $localized = new ProductDimensionContent(new Product());

        $this->mapper->map(new ProductDimensionContent(new Product()), $localized, []);

        $this->assertNull($localized->getMainCategory());
    }

    public function testMainCategorySetsMainCategoryAndExcerptCategory(): void
    {
        $category = $this->prophesize(CategoryInterface::class)->reveal();
        $this->categoryFactory->create([5])->willReturn([$category]);
        $localized = new ProductDimensionContent(new Product());

        $this->mapper->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => 5]);

        $this->assertSame($category, $localized->getMainCategory());
        $this->assertSame([$category], $localized->getExcerptCategories());
    }

    public function testMainCategoryNullRemovesMainCategoryAndExcerptCategory(): void
    {
        $category = $this->prophesize(CategoryInterface::class)->reveal();
        $localized = new ProductDimensionContent(new Product());
        $localized->setMainCategory($category);

        $this->mapper->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => null]);

        $this->assertNull($localized->getMainCategory());
        $this->assertSame([], $localized->getExcerptCategories());
    }

    public function testMainCategoryUnknownCategoryIdClearsMainCategory(): void
    {
        $this->categoryFactory->create([9])->willReturn([]);
        $localized = new ProductDimensionContent(new Product());

        $this->mapper->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => 9]);

        $this->assertNull($localized->getMainCategory());
    }

    public function testMainCategoryAcceptsACategoryBelowTheRoot(): void
    {
        $this->categoryRepository->findCategoryByKey('shop')->willReturn($this->createCategory(1, 1, 10));
        $category = $this->createCategory(2, 2, 3);
        $this->categoryFactory->create([2])->willReturn([$category]);
        $localized = new ProductDimensionContent(new Product());

        $this->createMapperWithRootKey('shop')->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => 2]);

        $this->assertSame($category, $localized->getMainCategory());
    }

    public function testMainCategoryRejectsACategoryOutsideTheRoot(): void
    {
        $this->categoryRepository->findCategoryByKey('shop')->willReturn($this->createCategory(1, 1, 10));
        $this->categoryFactory->create([3])->willReturn([$this->createCategory(3, 11, 12)]);
        $localized = new ProductDimensionContent(new Product());

        $this->expectException(InvalidMainCategoryException::class);

        $this->createMapperWithRootKey('shop')->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => 3]);
    }

    public function testMainCategoryRejectsTheRootItself(): void
    {
        $root = $this->createCategory(1, 1, 10);
        $this->categoryRepository->findCategoryByKey('shop')->willReturn($root);
        $this->categoryFactory->create([1])->willReturn([$root]);

        $this->expectException(InvalidMainCategoryException::class);

        $this->createMapperWithRootKey('shop')->map(
            new ProductDimensionContent(new Product()),
            new ProductDimensionContent(new Product()),
            ['mainCategory' => 1],
        );
    }

    public function testMainCategoryRejectsEveryCategoryWhenTheRootDoesNotExist(): void
    {
        $this->categoryRepository->findCategoryByKey('missing')->willReturn(null);
        $this->categoryFactory->create([2])->willReturn([$this->createCategory(2, 2, 3)]);

        $this->expectException(InvalidMainCategoryException::class);

        $this->createMapperWithRootKey('missing')->map(
            new ProductDimensionContent(new Product()),
            new ProductDimensionContent(new Product()),
            ['mainCategory' => 2],
        );
    }

    public function testMainCategoryKeepsAStoredCategoryOutsideTheRoot(): void
    {
        $stored = $this->createCategory(3, 11, 12);
        $this->categoryRepository->findCategoryByKey(Argument::cetera())->shouldNotBeCalled();
        $this->categoryFactory->create([3])->willReturn([$stored]);
        $localized = new ProductDimensionContent(new Product());
        $localized->setMainCategory($stored);

        $this->createMapperWithRootKey('shop')->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => 3]);

        $this->assertSame($stored, $localized->getMainCategory());
    }

    public function testMainCategoryClearingNeedsNoRootCheck(): void
    {
        $this->categoryRepository->findCategoryByKey(Argument::cetera())->shouldNotBeCalled();
        $localized = new ProductDimensionContent(new Product());
        $localized->setMainCategory($this->createCategory(3, 11, 12));

        $this->createMapperWithRootKey('shop')->map(new ProductDimensionContent(new Product()), $localized, ['mainCategory' => null]);

        $this->assertNull($localized->getMainCategory());
    }
}
