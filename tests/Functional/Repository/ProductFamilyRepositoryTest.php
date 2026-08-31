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
use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\ProductFamilyRepository;

#[CoversClass(ProductFamilyRepository::class)]
class ProductFamilyRepositoryTest extends SuluTestCase
{
    private ProductFamilyRepositoryInterface $repository;

    private ProductRepositoryInterface $productRepository;

    private AttributeRepositoryInterface $attributeRepository;

    private AttributeGroupRepositoryInterface $attributeGroupRepository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ProductFamilyRepositoryInterface $repository */
        $repository = $container->get(ProductFamilyRepositoryInterface::class);
        $this->repository = $repository;

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get(ProductRepositoryInterface::class);
        $this->productRepository = $productRepository;

        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        $this->attributeRepository = $attributeRepository;

        /** @var AttributeGroupRepositoryInterface $attributeGroupRepository */
        $attributeGroupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        $this->attributeGroupRepository = $attributeGroupRepository;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testCreateReturnsFamilyWithUuid(): void
    {
        $family = $this->repository->create();
        $this->assertNotNull($family->getUuid());
        $this->assertNotSame('', $family->getUuid());
    }

    public function testSavePersistsWithTranslationAndFindsByUuid(): void
    {
        $family = $this->repository->create();
        $translation = new ProductFamilyTranslation($family, 'en', 'My Family');
        $translation->setDescription('A description');
        $family->addTranslation($translation);
        $this->repository->save($family);
        $this->entityManager->flush();

        $uuid = $family->getUuid();
        $this->assertNotNull($uuid);
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertInstanceOf(ProductFamilyInterface::class, $loaded);
        $this->assertSame('My Family', $loaded->getTranslation('en')?->getName());
    }

    public function testFindOneByExternalIdentifierReturnsFamily(): void
    {
        $family = $this->repository->create();
        $family->setExternalIdentifier('ext-family-1');
        $this->repository->save($family);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['externalIdentifier' => 'ext-family-1']);
        $this->assertInstanceOf(ProductFamilyInterface::class, $loaded);
        $this->assertSame('ext-family-1', $loaded->getExternalIdentifier());
    }

    public function testFindOneByProductUuidReturnsOwningFamily(): void
    {
        $family = $this->repository->create();
        $this->repository->save($family);

        $product = $this->productRepository->createNew();
        $pdc = $product->createDimensionContent();
        $pdc->setProductFamily($family);
        $product->addDimensionContent($pdc);
        $this->productRepository->add($product);
        $this->entityManager->persist($pdc);
        $this->entityManager->flush();

        $familyUuid = $family->getUuid();
        $productUuid = $product->getUuid();
        $this->assertNotNull($familyUuid);
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['productUuid' => $productUuid]);
        $this->assertInstanceOf(ProductFamilyInterface::class, $loaded);
        $this->assertSame($familyUuid, $loaded->getUuid());
    }

    public function testFindByUuidsReturnsMatchingFamilies(): void
    {
        $family1 = $this->repository->create();
        $this->repository->save($family1);
        $family2 = $this->repository->create();
        $this->repository->save($family2);
        $family3 = $this->repository->create();
        $this->repository->save($family3);
        $this->entityManager->flush();

        $uuid1 = $family1->getUuid();
        $uuid2 = $family2->getUuid();
        $this->assertNotNull($uuid1);
        $this->assertNotNull($uuid2);
        $this->entityManager->clear();

        $result = [];
        foreach ($this->repository->findBy(['uuids' => [$uuid1, $uuid2]]) as $family) {
            $result[] = $family;
        }

        $this->assertCount(2, $result);
        $loadedUuids = \array_map(static fn (ProductFamilyInterface $family) => $family->getUuid(), $result);
        $this->assertContains($uuid1, $loadedUuids);
        $this->assertContains($uuid2, $loadedUuids);
    }

    public function testFindByWithoutFiltersReturnsAllFamilies(): void
    {
        $this->repository->save($this->repository->create());
        $this->entityManager->flush();
        $this->entityManager->clear();

        $result = [];
        foreach ($this->repository->findBy() as $family) {
            $result[] = $family;
        }

        $this->assertCount(1, $result);
    }

    public function testFindOneByReturnsNullForUnknownUuid(): void
    {
        $this->assertNull($this->repository->findOneBy(['uuid' => '00000000-0000-0000-0000-000000000000']));
    }

    public function testGetOneByThrowsForUnknownUuid(): void
    {
        $this->expectException(ProductFamilyNotFoundException::class);
        $this->repository->getOneBy(['uuid' => '00000000-0000-0000-0000-000000000000']);
    }

    public function testGetOneByReturnsFamilyWhenFound(): void
    {
        $family = $this->repository->create();
        $this->repository->save($family);
        $this->entityManager->flush();
        $uuid = $family->getUuid();
        $this->assertNotNull($uuid);
        $this->entityManager->clear();

        $this->assertSame($uuid, $this->repository->getOneBy(['uuid' => $uuid])->getUuid());
    }

    public function testRemoveDeletesFromDatabase(): void
    {
        $family = $this->repository->create();
        $this->repository->save($family);
        $this->entityManager->flush();
        $uuid = $family->getUuid();
        $this->assertNotNull($uuid);

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertNotNull($loaded);
        $this->repository->remove($loaded);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->repository->findOneBy(['uuid' => $uuid]));
    }

    public function testFindOneByWithoutSelectsLeavesTheAttributeGraphUnloaded(): void
    {
        $uuid = $this->createFamilyWithOptionAttribute('color');
        $this->entityManager->clear();

        $family = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertInstanceOf(ProductFamilyInterface::class, $family);

        $queriesBefore = $this->countQueries();
        $this->walkAttributeGraph($family, 'de');

        // Without the select group the walk goes back to the database.
        $this->assertGreaterThan($queriesBefore, $this->countQueries());
    }

    public function testFindOneByWithAttributesSelectLeavesTheOptionsUnloaded(): void
    {
        $uuid = $this->createFamilyWithOptionAttribute('color');
        $this->entityManager->clear();

        $family = $this->repository->findOneBy(
            ['uuid' => $uuid],
            [ProductFamilyRepositoryInterface::SELECT_FAMILY_ATTRIBUTES => true],
        );
        $this->assertInstanceOf(ProductFamilyInterface::class, $family);

        $familyAttributes = [...$family->getFamilyAttributes()];
        $this->assertCount(1, $familyAttributes);
        $attribute = $familyAttributes[0]->getAttribute();

        $queriesBefore = $this->countQueries();
        $this->assertSame('Farbe', $attribute->getTranslation('de')?->getName());
        $this->assertSame($queriesBefore, $this->countQueries(), 'the attribute translations come with the family');

        // The options are not part of this select.
        $this->assertCount(1, $attribute->getOptions());
        $this->assertGreaterThan($queriesBefore, $this->countQueries());
    }

    public function testFindByReturnsNothingWhenNoFamilyMatches(): void
    {
        $this->assertSame([], [...$this->repository->findBy(
            ['uuid' => '00000000-0000-0000-0000-000000000000'],
            [ProductFamilyRepositoryInterface::GROUP_SELECT_PRODUCT_FAMILY_FORM => true],
        )]);
    }

    public function testFindOneByWithFormGroupLoadsTheWholeAttributeGraph(): void
    {
        $uuid = $this->createFamilyWithOptionAttribute('color');
        $this->entityManager->clear();

        $family = $this->repository->findOneBy(
            ['uuid' => $uuid],
            [ProductFamilyRepositoryInterface::GROUP_SELECT_PRODUCT_FAMILY_FORM => true],
        );
        $this->assertInstanceOf(ProductFamilyInterface::class, $family);

        $queriesBefore = $this->countQueries();
        $names = $this->walkAttributeGraph($family, 'de');

        $this->assertSame($queriesBefore, $this->countQueries(), 'walking the attribute graph must not query');
        $this->assertSame(['Farbe & Form', 'Farbe', 'Rot'], $names);
    }

    public function testFindOneByWithFormGroupLoadsTheDefaultLocaleFallback(): void
    {
        $uuid = $this->createFamilyWithOptionAttribute('color');
        $this->entityManager->clear();

        $family = $this->repository->findOneBy(
            ['uuid' => $uuid],
            [ProductFamilyRepositoryInterface::GROUP_SELECT_PRODUCT_FAMILY_FORM => true],
        );
        $this->assertInstanceOf(ProductFamilyInterface::class, $family);

        $queriesBefore = $this->countQueries();
        // 'en' misses every translation, so the walk runs the default locale fallback.
        $names = $this->walkAttributeGraph($family, 'en');

        $this->assertSame($queriesBefore, $this->countQueries(), 'the locale fallback must not query');
        $this->assertSame(['Farbe & Form', 'Farbe', 'red'], $names);
    }

    public function testGetOneByWithFormGroupLoadsTheWholeAttributeGraph(): void
    {
        $uuid = $this->createFamilyWithOptionAttribute('color');
        $this->entityManager->clear();

        $family = $this->repository->getOneBy(
            ['uuid' => $uuid],
            [ProductFamilyRepositoryInterface::GROUP_SELECT_PRODUCT_FAMILY_FORM => true],
        );

        $queriesBefore = $this->countQueries();
        $names = $this->walkAttributeGraph($family, 'de');

        $this->assertSame($queriesBefore, $this->countQueries(), 'walking the attribute graph must not query');
        $this->assertSame(['Farbe & Form', 'Farbe', 'Rot'], $names);
    }

    public function testFindByWithFormGroupLoadsTheAttributeGraphOfEveryFamily(): void
    {
        $this->createFamilyWithOptionAttribute('color');
        $this->createFamilyWithOptionAttribute('size');
        $this->entityManager->clear();

        $families = [...$this->repository->findBy(
            [],
            [ProductFamilyRepositoryInterface::GROUP_SELECT_PRODUCT_FAMILY_FORM => true],
        )];
        $this->assertCount(2, $families);

        $queriesBefore = $this->countQueries();
        $names = [];
        foreach ($families as $family) {
            $names = [...$names, ...$this->walkAttributeGraph($family, 'de')];
        }

        $this->assertSame($queriesBefore, $this->countQueries(), 'walking the attribute graph must not query');
        $this->assertCount(6, $names);
    }

    private function createFamilyWithOptionAttribute(string $key): string
    {
        $group = $this->attributeGroupRepository->create();
        $group->setDefaultLocale('de');
        $group->addTranslation(new AttributeGroupTranslation($group, 'de', 'Farbe & Form'));
        $this->attributeGroupRepository->save($group);

        $attribute = $this->attributeRepository->create($group);
        $attribute->setKey($key);
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $attribute->setDefaultLocale('de');
        $attribute->addTranslation(new AttributeTranslation($attribute, 'de', 'Farbe'));

        $option = new AttributeOption($attribute, 'red');
        $option->addTranslation(new AttributeOptionTranslation($option, 'de', 'Rot'));
        $attribute->addOption($option);
        $this->attributeRepository->save($attribute);

        $family = $this->repository->create();
        $family->addFamilyAttribute(new ProductFamilyAttribute($family, $attribute));
        $this->repository->save($family);
        $this->entityManager->flush();

        $uuid = $family->getUuid();
        $this->assertNotNull($uuid);

        return $uuid;
    }

    /**
     * Walks the family the way the form metadata visitors do.
     *
     * @return list<string>
     */
    private function walkAttributeGraph(ProductFamilyInterface $family, string $locale): array
    {
        $names = [];

        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            $attribute = $familyAttribute->getAttribute();
            $group = $attribute->getGroup();

            $groupTranslation = $group->getTranslation($locale)
                ?? (($defaultLocale = $group->getDefaultLocale()) !== null ? $group->getTranslation($defaultLocale) : null);
            $names[] = $groupTranslation?->getName() ?? '';

            $attributeTranslation = $attribute->getTranslation($locale)
                ?? (($defaultLocale = $attribute->getDefaultLocale()) !== null ? $attribute->getTranslation($defaultLocale) : null);
            $names[] = $attributeTranslation?->getName() ?? $attribute->getKey();

            foreach ($attribute->getOptions() as $option) {
                $names[] = $option->getTranslation($locale)?->getName() ?? $option->getKey();
            }
        }

        return $names;
    }

    private function countQueries(): int
    {
        /** @var array<array{Value: string}> $rows */
        $rows = $this->entityManager->getConnection()
            ->executeQuery("SHOW SESSION STATUS LIKE 'Com_select'")
            ->fetchAllAssociative();

        return (int) $rows[0]['Value'];
    }
}
