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

namespace Sulu\Product\Tests\Functional\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\ProductRepository;
use Sulu\Route\Domain\Model\Route;

#[CoversClass(ProductRepository::class)]
class ProductRepositoryTest extends SuluTestCase
{
    private ProductRepositoryInterface $repository;

    private ProductRepository $doctrineRepository;

    private ProductFamilyRepositoryInterface $productFamilyRepository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ProductRepositoryInterface $repository */
        $repository = $container->get(ProductRepositoryInterface::class);
        $this->repository = $repository;
        if (!$repository instanceof ProductRepository) {
            throw new \UnexpectedValueException('Expected product repository service to use the Doctrine implementation.');
        }
        $this->doctrineRepository = $repository;

        /** @var ProductFamilyRepositoryInterface $productFamilyRepository */
        $productFamilyRepository = $container->get(ProductFamilyRepositoryInterface::class);
        $this->productFamilyRepository = $productFamilyRepository;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        self::purgeDatabase();
    }

    private function createFamily(): ProductFamilyInterface
    {
        $family = $this->productFamilyRepository->create();
        $this->productFamilyRepository->save($family);
        $this->entityManager->flush();

        return $family;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    private function createAndPersistProduct(?string $code = null): ProductInterface
    {
        $product = $this->repository->createNew();
        $pdc = $product->createDimensionContent();
        if (null !== $code) {
            $pdc->setCode($code);
        }
        $product->addDimensionContent($pdc);
        $this->repository->add($product);
        $this->entityManager->persist($pdc);
        $this->entityManager->flush();

        return $product;
    }

    public function testCreateNewReturnsFreshProductWithUuid(): void
    {
        $product = $this->repository->createNew();

        $this->assertNotSame('', $product->getUuid());
    }

    public function testCreateNewUsesProvidedUuid(): void
    {
        $uuid = '11111111-1111-1111-1111-111111111111';

        $product = $this->repository->createNew($uuid);

        $this->assertSame($uuid, $product->getUuid());
    }

    public function testCreateNewWithoutUuidReturnsUniqueUuids(): void
    {
        $a = $this->repository->createNew();
        $b = $this->repository->createNew();

        $this->assertNotSame($a->getUuid(), $b->getUuid());
    }

    public function testAddPersistsProductAndItCanBeReloaded(): void
    {
        $product = $this->repository->createNew();
        $pdc = $product->createDimensionContent();
        $pdc->setCode('PROD-ADD');
        $product->addDimensionContent($pdc);
        $this->repository->add($product);
        $this->entityManager->persist($pdc);
        $this->entityManager->flush();

        $uuid = $product->getUuid();
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);

        $this->assertInstanceOf(ProductInterface::class, $loaded);
        $this->assertSame($uuid, $loaded->getUuid());
    }

    public function testGetOneByReturnsProductForMatchingUuid(): void
    {
        $product = $this->createAndPersistProduct('PROD-1');
        $uuid = $product->getUuid();

        $this->entityManager->clear();

        $loaded = $this->repository->getOneBy(['uuid' => $uuid]);

        $this->assertSame($uuid, $loaded->getUuid());
    }

    public function testGetOneByThrowsProductNotFoundExceptionForUnknownUuid(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->repository->getOneBy(['uuid' => '00000000-0000-0000-0000-000000000000']);
    }

    public function testFindOneByReturnsProductForMatchingUuid(): void
    {
        $product = $this->createAndPersistProduct('PROD-2');
        $uuid = $product->getUuid();

        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);

        $this->assertInstanceOf(ProductInterface::class, $loaded);
        $this->assertSame($uuid, $loaded->getUuid());
    }

    public function testFindOneByReturnsNullForUnknownUuid(): void
    {
        $loaded = $this->repository->findOneBy(['uuid' => '00000000-0000-0000-0000-000000000000']);

        $this->assertNull($loaded);
    }

    public function testExistByReturnsTrueWhenProductWithCodeExists(): void
    {
        $this->createAndPersistProduct('CODE-EXISTS');
        $this->entityManager->clear();

        $this->assertTrue($this->repository->existBy(['code' => 'CODE-EXISTS']));
    }

    public function testExistByReturnsFalseWhenCodeDoesNotMatch(): void
    {
        $this->createAndPersistProduct('CODE-A');
        $this->entityManager->clear();

        $this->assertFalse($this->repository->existBy(['code' => 'CODE-DOES-NOT-EXIST']));
    }

    public function testExistByWithoutCodeReturnsTrueWhenAnyProductExists(): void
    {
        $this->createAndPersistProduct('CODE-ANY');
        $this->entityManager->clear();

        // existBy without filters falls through to COUNT — any persisted product counts
        $this->assertTrue($this->repository->existBy([]));
    }

    public function testExistByOnEmptyDatabaseReturnsFalse(): void
    {
        $this->assertFalse($this->repository->existBy(['code' => 'ANY']));
        $this->assertFalse($this->repository->existBy([]));
    }

    public function testExistByExcludeUuidExcludesTheGivenProduct(): void
    {
        $product = $this->createAndPersistProduct('CODE-EXCLUDE');
        $uuid = $product->getUuid();
        $this->entityManager->clear();

        // the only matching product is excluded via excludeUuid -> no match left
        $this->assertFalse($this->repository->existBy(['code' => 'CODE-EXCLUDE', 'excludeUuid' => $uuid]));
    }

    public function testExistByExcludeUuidStillMatchesOtherProducts(): void
    {
        $a = $this->createAndPersistProduct('CODE-KEEP');
        $b = $this->createAndPersistProduct('CODE-OTHER');
        $this->entityManager->clear();

        // excluding a different uuid than the matching product's uuid still finds it
        $this->assertTrue($this->repository->existBy(['code' => 'CODE-KEEP', 'excludeUuid' => $b->getUuid()]));
        self::assertNotSame($a->getUuid(), $b->getUuid());
    }

    public function testExistByProductFamilyUuidMatchesAssignedProducts(): void
    {
        $family = $this->createFamily();

        $product = $this->repository->createNew();
        $pdc = $product->createDimensionContent();
        $pdc->setProductFamily($family);
        $product->addDimensionContent($pdc);
        $this->repository->add($product);
        $this->entityManager->persist($pdc);
        $this->entityManager->flush();

        $familyUuid = $family->getUuid();
        self::assertNotNull($familyUuid);
        $this->entityManager->clear();

        $this->assertTrue($this->repository->existBy(['productFamilyUuid' => $familyUuid]));
        $this->assertFalse($this->repository->existBy(['productFamilyUuid' => '00000000-0000-0000-0000-000000000000']));
    }

    public function testCountByReturnsTotalCount(): void
    {
        $this->createAndPersistProduct('C1');
        $this->createAndPersistProduct('C2');
        $this->createAndPersistProduct('C3');
        $this->entityManager->clear();

        $this->assertSame(3, $this->repository->countBy());
    }

    public function testCountByIgnoresPageAndLimit(): void
    {
        $this->createAndPersistProduct('C1');
        $this->createAndPersistProduct('C2');
        $this->createAndPersistProduct('C3');
        $this->entityManager->clear();

        // page and limit should be stripped — full count returned
        $this->assertSame(3, $this->repository->countBy(['page' => 1, 'limit' => 1])); // @phpstan-ignore-line
    }

    public function testCountByWithUuidFilter(): void
    {
        $p1 = $this->createAndPersistProduct('CC1');
        $this->createAndPersistProduct('CC2');
        $this->entityManager->clear();

        $this->assertSame(1, $this->repository->countBy(['uuid' => $p1->getUuid()]));
    }

    public function testCountByWithUuidsFilter(): void
    {
        $p1 = $this->createAndPersistProduct('U1');
        $p2 = $this->createAndPersistProduct('U2');
        $this->createAndPersistProduct('U3');
        $this->entityManager->clear();

        $count = $this->repository->countBy(['uuids' => [$p1->getUuid(), $p2->getUuid()]]);

        $this->assertSame(2, $count);
    }

    public function testCountByOnEmptyDatabaseReturnsZero(): void
    {
        $this->assertSame(0, $this->repository->countBy());
    }

    public function testFindByReturnsAllPersistedProducts(): void
    {
        $a = $this->createAndPersistProduct('F1');
        $b = $this->createAndPersistProduct('F2');
        $this->entityManager->clear();

        $generator = $this->repository->findBy();
        $this->assertInstanceOf(\Generator::class, $generator);

        $uuids = [];
        foreach ($generator as $product) {
            $uuids[] = $product->getUuid();
        }

        \sort($uuids);
        $expected = [$a->getUuid(), $b->getUuid()];
        \sort($expected);

        $this->assertSame($expected, $uuids);
    }

    public function testFindByWithUuidFilter(): void
    {
        $a = $this->createAndPersistProduct('FA');
        $this->createAndPersistProduct('FB');
        $this->entityManager->clear();

        $products = \iterator_to_array($this->repository->findBy(['uuid' => $a->getUuid()]), false);

        $this->assertCount(1, $products);
        $this->assertSame($a->getUuid(), $products[0]->getUuid());
    }

    public function testFindByWithUuidsFilter(): void
    {
        $a = $this->createAndPersistProduct('FUA');
        $b = $this->createAndPersistProduct('FUB');
        $this->createAndPersistProduct('FUC');
        $this->entityManager->clear();

        $products = \iterator_to_array(
            $this->repository->findBy(['uuids' => [$a->getUuid(), $b->getUuid()]]),
            false,
        );

        $uuids = \array_map(static fn (ProductInterface $p) => $p->getUuid(), $products);
        \sort($uuids);
        $expected = [$a->getUuid(), $b->getUuid()];
        \sort($expected);

        $this->assertSame($expected, $uuids);
    }

    public function testFindByParentFilterReturnsOnlyChildrenOfGivenParent(): void
    {
        $parent = $this->createAndPersistProduct('PARENT-VARIANTS');

        $child1 = $this->repository->createNew();
        $child1->setParent($parent);
        $pdc1 = $child1->createDimensionContent();
        $child1->addDimensionContent($pdc1);
        $this->repository->add($child1);
        $this->entityManager->persist($pdc1);

        $child2 = $this->repository->createNew();
        $child2->setParent($parent);
        $pdc2 = $child2->createDimensionContent();
        $child2->addDimensionContent($pdc2);
        $this->repository->add($child2);
        $this->entityManager->persist($pdc2);

        // an unrelated top-level product should not be picked up by the parent filter
        $this->createAndPersistProduct('UNRELATED');

        $this->entityManager->flush();
        $this->entityManager->clear();

        $products = \iterator_to_array($this->repository->findBy(['parent' => $parent->getUuid()]), false);

        $uuids = \array_map(static fn (ProductInterface $p) => $p->getUuid(), $products);
        \sort($uuids);
        $expected = [$child1->getUuid(), $child2->getUuid()];
        \sort($expected);

        $this->assertCount(2, $products);
        $this->assertSame($expected, $uuids);
    }

    public function testFindByExcludeTypesExcludesVariants(): void
    {
        [$parent, $child] = $this->createAndPersistVariantPair('PARENT-EXCLUDE');

        $products = \iterator_to_array(
            $this->repository->findBy(['excludeTypes' => [ProductInterface::TYPE_VARIANT]]),
            false,
        );
        $uuids = \array_map(static fn (ProductInterface $p) => $p->getUuid(), $products);

        $this->assertContains($parent->getUuid(), $uuids);
        $this->assertNotContains($child->getUuid(), $uuids);
    }

    public function testFindByTypesOnlyReturnsGivenTypes(): void
    {
        [$parent, $child] = $this->createAndPersistVariantPair('PARENT-TYPES');

        $products = \iterator_to_array(
            $this->repository->findBy(['types' => [ProductInterface::TYPE_VARIANT]]),
            false,
        );
        $uuids = \array_map(static fn (ProductInterface $p) => $p->getUuid(), $products);

        $this->assertContains($child->getUuid(), $uuids);
        $this->assertNotContains($parent->getUuid(), $uuids);
    }

    /**
     * @return array{ProductInterface, ProductInterface}
     */
    private function createAndPersistVariantPair(string $code): array
    {
        $parent = $this->createAndPersistProduct($code);
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $child = $this->repository->createNew();
        $child->setType(ProductInterface::TYPE_VARIANT);
        $child->setParent($parent);
        $pdc = $child->createDimensionContent();
        $child->addDimensionContent($pdc);
        $this->repository->add($child);
        $this->entityManager->persist($pdc);

        $this->entityManager->flush();
        $this->entityManager->clear();

        return [$parent, $child];
    }

    public function testFindByPaginationLimit(): void
    {
        $this->createAndPersistProduct('P1');
        $this->createAndPersistProduct('P2');
        $this->createAndPersistProduct('P3');
        $this->entityManager->clear();

        $products = \iterator_to_array($this->repository->findBy(['limit' => 2]), false);

        $this->assertCount(2, $products);
    }

    public function testFindByPaginationPageAndLimit(): void
    {
        $this->createAndPersistProduct('PP1');
        $this->createAndPersistProduct('PP2');
        $this->createAndPersistProduct('PP3');
        $this->entityManager->clear();

        $page1 = \iterator_to_array(
            $this->repository->findBy(['limit' => 2, 'page' => 1], ['uuid' => 'asc']),
            false,
        );
        $page2 = \iterator_to_array(
            $this->repository->findBy(['limit' => 2, 'page' => 2], ['uuid' => 'asc']),
            false,
        );

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);

        $allUuids = \array_merge(
            \array_map(static fn (ProductInterface $p) => $p->getUuid(), $page1),
            \array_map(static fn (ProductInterface $p) => $p->getUuid(), $page2),
        );

        $this->assertCount(3, \array_unique($allUuids));
    }

    public function testFindBySortByUuidAsc(): void
    {
        $this->createAndPersistProduct('S1');
        $this->createAndPersistProduct('S2');
        $this->createAndPersistProduct('S3');
        $this->entityManager->clear();

        $products = \iterator_to_array($this->repository->findBy([], ['uuid' => 'asc']), false);

        $uuids = \array_map(static fn (ProductInterface $p) => $p->getUuid(), $products);
        $sorted = $uuids;
        \sort($sorted);

        $this->assertSame($sorted, $uuids);
    }

    public function testFindBySortByUuidDesc(): void
    {
        $this->createAndPersistProduct('SD1');
        $this->createAndPersistProduct('SD2');
        $this->createAndPersistProduct('SD3');
        $this->entityManager->clear();

        $products = \iterator_to_array($this->repository->findBy([], ['uuid' => 'desc']), false);

        $uuids = \array_map(static fn (ProductInterface $p) => $p->getUuid(), $products);
        $sorted = $uuids;
        \rsort($sorted);

        $this->assertSame($sorted, $uuids);
    }

    public function testFindByOnEmptyDatabaseYieldsNothing(): void
    {
        $products = \iterator_to_array($this->repository->findBy(), false);

        $this->assertSame([], $products);
    }

    public function testFindIdentifiersByReturnsUuids(): void
    {
        $a = $this->createAndPersistProduct('I1');
        $b = $this->createAndPersistProduct('I2');
        $this->entityManager->clear();

        $result = $this->repository->findIdentifiersBy();
        $ids = \is_array($result) ? $result : \iterator_to_array($result, false);

        \sort($ids);
        $expected = [$a->getUuid(), $b->getUuid()];
        \sort($expected);

        $this->assertSame($expected, $ids);
    }

    public function testFindIdentifiersByWithUuidFilter(): void
    {
        $a = $this->createAndPersistProduct('II1');
        $this->createAndPersistProduct('II2');
        $this->entityManager->clear();

        $result = $this->repository->findIdentifiersBy(['uuid' => $a->getUuid()]);
        $ids = \is_array($result) ? $result : \iterator_to_array($result, false);

        $this->assertSame([$a->getUuid()], \array_values($ids));
    }

    public function testFindIdentifiersByWithSortByUuid(): void
    {
        $this->createAndPersistProduct('IS1');
        $this->createAndPersistProduct('IS2');
        $this->createAndPersistProduct('IS3');
        $this->entityManager->clear();

        $result = $this->repository->findIdentifiersBy([], ['uuid' => 'asc']);
        $ids = \is_array($result) ? $result : \iterator_to_array($result, false);

        $ids = \array_values($ids);
        $sorted = $ids;
        \sort($sorted);

        $this->assertSame($sorted, $ids);
    }

    public function testRemoveDeletesProductFromDatabase(): void
    {
        $product = $this->createAndPersistProduct('TO-REMOVE');
        $uuid = $product->getUuid();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertInstanceOf(ProductInterface::class, $loaded);

        $this->repository->remove($loaded);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->repository->findOneBy(['uuid' => $uuid]));
        $this->assertFalse($this->repository->existBy(['code' => 'TO-REMOVE']));
    }

    public function testRemoveDimensionContentDeletesAttachedDimensionContent(): void
    {
        $product = $this->repository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('draft');

        $product->addDimensionContent($dimensionContent);

        $this->repository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $uuid = $product->getUuid();
        $this->entityManager->clear();

        // Reload and ensure dimension content exists
        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertInstanceOf(ProductInterface::class, $loaded);

        $dimensionContents = $loaded->getDimensionContents()->toArray();
        $this->assertCount(1, $dimensionContents);

        $this->repository->removeDimensionContent($dimensionContents[0]);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertInstanceOf(ProductInterface::class, $reloaded);
        $this->assertCount(0, $reloaded->getDimensionContents()->toArray());
    }

    public function testCreateQueryBuilderReturnsQueryBuilder(): void
    {
        $qb = $this->doctrineRepository->createQueryBuilder([]);

        $this->assertSame(QueryBuilder::class, $qb::class);
    }

    public function testCreateQueryBuilderWithUuidFilterReturnsExpectedResult(): void
    {
        $product = $this->createAndPersistProduct('QB');
        $uuid = $product->getUuid();
        $this->entityManager->clear();

        $qb = $this->doctrineRepository->createQueryBuilder(['uuid' => $uuid]);
        /** @var ProductInterface[] $result */
        $result = $qb->getQuery()->getResult();

        $this->assertCount(1, $result);
        $this->assertSame($uuid, $result[0]->getUuid());
    }

    /**
     * The caller's key order is the ORDER BY order. `DimensionContentQueryEnhancer::addFilters()`
     * also knows `created`, so it used to append it ahead of `position` and decide the order alone.
     */
    public function testCreateQueryBuilderSortsInTheOrderTheKeysWereGiven(): void
    {
        $qb = $this->doctrineRepository->createQueryBuilder(
            ['locale' => 'en', 'stage' => 'live'],
            ['position' => 'asc', 'created' => 'asc', 'uuid' => 'asc'],
        );

        $this->assertStringEndsWith(
            'ORDER BY product.position asc, product.created asc, product.uuid asc',
            $qb->getDQL(),
        );
        // getDQL() only concatenates the parts; an unmapped field fails when the query is parsed.
        $qb->getQuery()->getResult();
    }

    /** A dimension content field keeps its place among the product's own. */
    public function testCreateQueryBuilderSortsAcrossBothAliasesInOneOrder(): void
    {
        $qb = $this->doctrineRepository->createQueryBuilder(
            ['locale' => 'en', 'stage' => 'live'],
            ['position' => 'asc', 'title' => 'desc'],
        );

        $this->assertStringEndsWith(
            'ORDER BY product.position asc, filterDimensionContent.title desc',
            $qb->getDQL(),
        );
        $qb->getQuery()->getResult();
    }

    /** Every alias maps to a real column, including the ones no caller in this bundle passes. */
    public function testCreateQueryBuilderSortsByEveryKnownField(): void
    {
        $qb = $this->doctrineRepository->createQueryBuilder(
            ['locale' => 'en', 'stage' => 'live'],
            [
                'uuid' => 'asc',
                'position' => 'asc',
                'created' => 'asc',
                'changed' => 'desc',
                'title' => 'asc',
                'authored' => 'desc',
                'workflowPublished' => 'desc',
            ],
        );

        $this->assertStringEndsWith(
            'ORDER BY product.uuid asc, product.position asc, product.created asc, product.changed desc,'
            . ' filterDimensionContent.title asc, filterDimensionContent.authored desc,'
            . ' filterDimensionContent.workflowPublished desc',
            $qb->getDQL(),
        );
        $qb->getQuery()->getResult();
    }

    /** The clause order decides the row order, not just the DQL string. */
    public function testCreateQueryBuilderReturnsRowsInTheRequestedOrder(): void
    {
        $first = $this->createLiveProduct('en', 'B', 0);
        $last = $this->createLiveProduct('en', 'A', 1);
        $this->entityManager->clear();

        $byPosition = $this->doctrineRepository->createQueryBuilder(
            ['locale' => 'en', 'stage' => 'live'],
            ['position' => 'asc', 'title' => 'asc'],
        );

        /** @var ProductInterface[] $result */
        $result = $byPosition->getQuery()->getResult();
        $this->assertSame(
            [$first->getUuid(), $last->getUuid()],
            [$result[0]->getUuid(), $result[1]->getUuid()],
        );

        // Same two rows, and the dimension content field now decides: the order flips.
        $byTitle = $this->doctrineRepository->createQueryBuilder(
            ['locale' => 'en', 'stage' => 'live'],
            ['title' => 'asc', 'position' => 'asc'],
        );

        /** @var ProductInterface[] $flipped */
        $flipped = $byTitle->getQuery()->getResult();
        $this->assertSame(
            [$last->getUuid(), $first->getUuid()],
            [$flipped[0]->getUuid(), $flipped[1]->getUuid()],
        );
    }

    /**
     * Without `locale` and `stage` the dimension content is not joined. A field we know but cannot
     * reach would order the rows arbitrarily, so it is a caller mistake rather than a field to skip.
     */
    public function testCreateQueryBuilderRejectsDimensionContentFieldsWithoutTheJoin(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sorting by "title" requires both "locale" and "stage" filters.');

        $this->doctrineRepository->createQueryBuilder(
            ['uuids' => ['01a01e90-f47c-7822-8206-b3464acc3a13']],
            ['title' => 'asc', 'position' => 'asc'],
        );
    }

    /** An unknown field stays silent: the caller cannot have meant a column that does not exist. */
    public function testCreateQueryBuilderIgnoresUnknownSortFields(): void
    {
        $qb = $this->doctrineRepository->createQueryBuilder(
            ['uuids' => ['01a01e90-f47c-7822-8206-b3464acc3a13']],
            ['nonExistingField' => 'asc', 'position' => 'asc'],
        );

        $this->assertStringEndsWith('ORDER BY product.position asc', $qb->getDQL());
        $qb->getQuery()->getResult();
    }

    public function testFindSlugsByReturnsTheSlugOfEveryRequestedProduct(): void
    {
        $withRoute = $this->createLiveProduct('en', 'Routed', 0, '/routed');
        $withoutRoute = $this->createLiveProduct('en', 'Unrouted', 1);
        $this->entityManager->clear();

        $slugs = $this->repository->findSlugsBy([
            'uuids' => [$withRoute->getUuid(), $withoutRoute->getUuid()],
            'locale' => 'en',
            'stage' => 'live',
        ]);

        $this->assertSame(
            [$withRoute->getUuid() => '/routed', $withoutRoute->getUuid() => null],
            $slugs,
        );
    }

    /** No dimension content in that locale and stage means no entry, which is not the same as null. */
    public function testFindSlugsByOmitsProductsWithoutContentInTheRequestedDimension(): void
    {
        $product = $this->createLiveProduct('en', 'Routed', 0, '/routed');
        $this->entityManager->clear();

        $this->assertSame([], $this->repository->findSlugsBy([
            'uuids' => [$product->getUuid()],
            'locale' => 'de',
            'stage' => 'live',
        ]));

        $this->assertSame([], $this->repository->findSlugsBy([
            'uuids' => [$product->getUuid()],
            'locale' => 'en',
            'stage' => 'draft',
        ]));
    }

    public function testFindSlugsByWithoutUuidsDoesNotQuery(): void
    {
        $this->assertSame([], $this->repository->findSlugsBy([
            'uuids' => [],
            'locale' => 'en',
            'stage' => 'live',
        ]));
    }

    private function createLiveProduct(
        string $locale,
        string $title,
        int $position,
        ?string $slug = null,
    ): ProductInterface {
        $product = $this->repository->createNew();
        $product->setPosition($position);

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale($locale);
        $dimensionContent->setStage('live');
        $dimensionContent->setTitle($title);

        // The route association carries no cascade, so it is persisted on its own.
        if (null !== $slug) {
            $route = new Route(ProductInterface::RESOURCE_KEY, $product->getUuid(), $locale, $slug);
            $dimensionContent->setRoute($route);
            $this->entityManager->persist($route);
        }

        $product->addDimensionContent($dimensionContent);
        $this->repository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        return $product;
    }

    public function testCreateQueryBuilderWithAdminGroupSelectIncludesDimensionContents(): void
    {
        $product = $this->repository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('draft');
        $product->addDimensionContent($dimensionContent);

        $this->repository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();
        $uuid = $product->getUuid();
        $this->entityManager->clear();

        // The admin select group resolves dimension contents for a concrete
        // locale + stage; without them getEffectiveDimensionAttributes() defaults
        // locale to null and the criteria filters to "locale IS NULL", excluding
        // the localized dimension content created above.
        $qb = $this->doctrineRepository->createQueryBuilder(
            ['uuid' => $uuid, 'locale' => 'en', 'stage' => 'draft'],
            [],
            [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_ADMIN => true],
        );

        /** @var ProductInterface[] $result */
        $result = $qb->getQuery()->getResult();

        $this->assertCount(1, $result);
        $this->assertSame($uuid, $result[0]->getUuid());
    }

    /**
     * Branch B: sort by 'created' field triggers the elseif branch in createQueryBuilder.
     * We pass a uuid filter (non-empty $filters) so the dimension-content enhancer's
     * empty-filter+sortBy shortcut is NOT triggered; only the sortBy loop is exercised.
     */
    public function testFindBySortByCreatedAsc(): void
    {
        $p1 = $this->createAndPersistProduct('CR1');
        $p2 = $this->createAndPersistProduct('CR2');
        $this->entityManager->clear();

        $products = \iterator_to_array(
            $this->repository->findBy(
                ['uuids' => [$p1->getUuid(), $p2->getUuid()]],
                ['created' => 'asc'],
            ),
            false,
        );

        $this->assertCount(2, $products);
    }

    /**
     * Branch B2: sort by 'position' triggers the third elseif in createQueryBuilder. As above, a
     * non-empty filter keeps the enhancer's empty-filter+sortBy shortcut out of the way.
     */
    public function testFindBySortByPositionAsc(): void
    {
        $first = $this->createAndPersistProduct('POS1');
        $second = $this->createAndPersistProduct('POS2');
        $first->setPosition(2);
        $second->setPosition(1);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $products = \iterator_to_array(
            $this->repository->findBy(
                ['uuids' => [$first->getUuid(), $second->getUuid()]],
                ['position' => 'asc'],
            ),
            false,
        );

        $this->assertCount(2, $products);
        $this->assertSame(
            [$second->getUuid(), $first->getUuid()],
            \array_map(static fn (ProductInterface $product): string => $product->getUuid(), $products),
        );
    }

    /**
     * Branch C: passing a falsy value for a select group key causes the loop to `continue`
     * without expanding the group. The query should still return products normally.
     */
    public function testCreateQueryBuilderWithFalsySelectGroupIsSkipped(): void
    {
        $product = $this->createAndPersistProduct('FALSY');
        $this->entityManager->clear();

        $qb = $this->doctrineRepository->createQueryBuilder(
            [],
            [],
            [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_ADMIN => false],
        );

        /** @var ProductInterface[] $result */
        $result = $qb->getQuery()->getResult();

        $uuids = \array_map(static fn (ProductInterface $p) => $p->getUuid(), $result);
        $this->assertContains($product->getUuid(), $uuids);
    }

    /**
     * Branch D: GROUP_SELECT_PRODUCT_WEBSITE expands SELECT_PRODUCT_CONTENT with the
     * website content-admin select group, hitting the website branch of the SELECTS constant.
     */
    public function testCreateQueryBuilderWithWebsiteGroupSelectIncludesDimensionContents(): void
    {
        $product = $this->repository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('live');
        $product->addDimensionContent($dimensionContent);

        $this->repository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();
        $uuid = $product->getUuid();
        $this->entityManager->clear();

        $qb = $this->doctrineRepository->createQueryBuilder(
            ['uuid' => $uuid, 'locale' => 'en', 'stage' => 'live'],
            [],
            [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true],
        );

        /** @var ProductInterface[] $result */
        $result = $qb->getQuery()->getResult();

        $this->assertCount(1, $result);
        $this->assertSame($uuid, $result[0]->getUuid());
    }

    /**
     * Branch E: SELECT_PRODUCT_CONTENT with an explicit 'dimensionAttributes' key hits lines 307-308
     * in createQueryBuilder, where $contentSelects and $dimensionAttributes are read from sub-keys.
     */
    public function testCreateQueryBuilderWithExplicitDimensionAttributes(): void
    {
        $product = $this->repository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('draft');
        $product->addDimensionContent($dimensionContent);

        $this->repository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();
        $uuid = $product->getUuid();
        $this->entityManager->clear();

        // This is the key: SELECT_PRODUCT_CONTENT with explicit 'dimensionAttributes' key
        // hits lines 307-308 in createQueryBuilder
        $qb = $this->doctrineRepository->createQueryBuilder(
            ['uuid' => $uuid],
            [],
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    'dimensionAttributes' => [
                        'locale' => 'en',
                        'stage' => 'draft',
                    ],
                    'selects' => [
                        DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true,
                    ],
                ],
            ],
        );

        /** @var \Sulu\Product\Domain\Model\ProductInterface[] $result */
        $result = $qb->getQuery()->getResult();
        $this->assertCount(1, $result);
        $this->assertSame($uuid, $result[0]->getUuid());
    }

    /**
     * Branch A: SELECT_PRODUCT_CONTENT is set directly without a `dimensionAttributes` sub-key.
     * This hits the else branch (lines 309-312) where $contentSelects = $contentConfig
     * and $dimensionAttributes = $filters.
     */
    public function testCreateQueryBuilderWithSelectProductContentWithoutDimensionAttributes(): void
    {
        $product = $this->repository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('draft');
        $product->addDimensionContent($dimensionContent);

        $this->repository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();
        $uuid = $product->getUuid();
        $this->entityManager->clear();

        // Pass SELECT_PRODUCT_CONTENT directly as an array WITHOUT a 'dimensionAttributes' key.
        // The value is an array of content-enhancer selects — no 'dimensionAttributes' sub-key,
        // triggering the else branch: $contentSelects = $contentConfig, $dimensionAttributes = $filters.
        $qb = $this->doctrineRepository->createQueryBuilder(
            ['uuid' => $uuid, 'locale' => 'en', 'stage' => 'draft'],
            [],
            [
                ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                    DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true,
                ],
            ],
        );

        /** @var ProductInterface[] $result */
        $result = $qb->getQuery()->getResult();

        $this->assertCount(1, $result);
        $this->assertSame($uuid, $result[0]->getUuid());
    }
}
