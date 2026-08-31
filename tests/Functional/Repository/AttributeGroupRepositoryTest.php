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
use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\AttributeGroupRepository;

#[CoversClass(AttributeGroupRepository::class)]
class AttributeGroupRepositoryTest extends SuluTestCase
{
    private AttributeGroupRepositoryInterface $repository;

    private AttributeRepositoryInterface $attributeRepository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var AttributeGroupRepositoryInterface $repository */
        $repository = $container->get(AttributeGroupRepositoryInterface::class);
        $this->repository = $repository;

        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        $this->attributeRepository = $attributeRepository;

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

    public function testCreateReturnsFreshAttributeGroupWithUuid(): void
    {
        $group = $this->repository->create();
        $this->assertNotNull($group->getUuid());
        $this->assertNotSame('', $group->getUuid());
    }

    public function testCreateGeneratesUniqueUuids(): void
    {
        $a = $this->repository->create();
        $b = $this->repository->create();
        $this->assertNotSame($a->getUuid(), $b->getUuid());
    }

    public function testFindByWithoutFiltersReturnsAllGroups(): void
    {
        $this->assertSame([], [...$this->repository->findBy()]);

        $a = $this->repository->create();
        $b = $this->repository->create();
        $this->repository->save($a);
        $this->repository->save($b);
        $this->entityManager->flush();

        $this->assertCount(2, [...$this->repository->findBy()]);
    }

    public function testFindByFiltersAndSorts(): void
    {
        $a = $this->repository->create();
        $a->setExternalIdentifier('group-a');
        $b = $this->repository->create();
        $b->setExternalIdentifier('group-b');
        $this->repository->save($a);
        $this->repository->save($b);
        $this->entityManager->flush();

        $filtered = [...$this->repository->findBy(['externalIdentifier' => 'group-b'])];
        $this->assertCount(1, $filtered);
        $this->assertSame('group-b', $filtered[0]->getExternalIdentifier());

        $sorted = [...$this->repository->findBy([], ['externalIdentifier' => 'desc'])];
        $identifiers = [];
        foreach ($sorted as $group) {
            $identifiers[] = $group->getExternalIdentifier();
        }
        $this->assertSame(['group-b', 'group-a'], $identifiers);

        $byId = [...$this->repository->findBy([], ['id' => 'desc'])];
        $this->assertSame([$b->getId(), $a->getId()], [$byId[0]->getId(), $byId[1]->getId()]);
    }

    public function testFindByWithAttributesSelectLeavesTheGroupTranslationsUnloaded(): void
    {
        $this->createGroupWithTranslatedAttribute();
        $this->entityManager->clear();

        $groups = [...$this->repository->findBy(
            [],
            [],
            [AttributeGroupRepositoryInterface::SELECT_GROUP_ATTRIBUTES => true],
        )];
        $this->assertCount(1, $groups);

        $queriesBefore = $this->countQueries();
        $this->assertCount(1, [...$groups[0]->getGroupAttributes()]);
        $this->assertSame($queriesBefore, $this->countQueries(), 'the attributes come with the group');

        // The group translations are not part of this select.
        $this->assertNotNull($groups[0]->getTranslation('de'));
        $this->assertGreaterThan($queriesBefore, $this->countQueries());
    }

    public function testFindByIgnoresUnknownSortFields(): void
    {
        $group = $this->repository->create();
        $this->repository->save($group);
        $this->entityManager->flush();

        $this->assertCount(1, [...$this->repository->findBy([], ['name; DROP' => 'desc'])]);
    }

    public function testFindByWithFormGroupLoadsTheAttributeGraph(): void
    {
        $this->createGroupWithTranslatedAttribute();
        $this->entityManager->clear();

        $groups = [...$this->repository->findBy(
            [],
            [],
            [AttributeGroupRepositoryInterface::GROUP_SELECT_PRODUCT_FAMILY_FORM => true],
        )];
        $this->assertCount(1, $groups);

        $queriesBefore = $this->countQueries();
        $names = $this->walkGroup($groups[0], 'de');

        $this->assertSame($queriesBefore, $this->countQueries(), 'walking the group must not query');
        $this->assertSame(['Farbe & Form', 'Farbe'], $names);
    }

    public function testFindByWithoutSelectsLeavesTheAttributeGraphUnloaded(): void
    {
        $this->createGroupWithTranslatedAttribute();
        $this->entityManager->clear();

        $groups = [...$this->repository->findBy()];
        $this->assertCount(1, $groups);

        $queriesBefore = $this->countQueries();
        $this->walkGroup($groups[0], 'de');

        // Without the select group the walk goes back to the database.
        $this->assertGreaterThan($queriesBefore, $this->countQueries());
    }

    public function testSavePersistsAndCanBeFoundByUuid(): void
    {
        $group = $this->repository->create();
        $this->repository->save($group);
        $this->entityManager->flush();

        $uuid = $group->getUuid();
        $this->assertNotNull($uuid);
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertInstanceOf(AttributeGroupInterface::class, $loaded);
        $this->assertSame($uuid, $loaded->getUuid());
    }

    public function testFindOneByExternalIdentifierReturnsGroup(): void
    {
        $group = $this->repository->create();
        $group->setExternalIdentifier('ext-group-1');
        $this->repository->save($group);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['externalIdentifier' => 'ext-group-1']);
        $this->assertInstanceOf(AttributeGroupInterface::class, $loaded);
        $this->assertSame('ext-group-1', $loaded->getExternalIdentifier());
    }

    public function testFindOneByReturnsNullForUnknownUuid(): void
    {
        $this->assertNull($this->repository->findOneBy(['uuid' => '00000000-0000-0000-0000-000000000000']));
    }

    public function testFindOneByLoadsTranslation(): void
    {
        $group = $this->repository->create();
        $translation = new AttributeGroupTranslation($group, 'en', 'My Group');
        $translation->setDescription('A description');
        $group->addTranslation($translation);
        $this->repository->save($group);
        $this->entityManager->flush();

        $uuid = $group->getUuid();
        $this->assertNotNull($uuid);
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertNotNull($loaded);
        $t = $loaded->getTranslation('en');
        $this->assertNotNull($t);
        $this->assertSame('My Group', $t->getName());
        $this->assertSame('A description', $t->getDescription());
    }

    public function testRemoveDeletesFromDatabase(): void
    {
        $group = $this->repository->create();
        $this->repository->save($group);
        $this->entityManager->flush();

        $uuid = $group->getUuid();
        $this->assertNotNull($uuid);

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertNotNull($loaded);

        $this->repository->remove($loaded);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->repository->findOneBy(['uuid' => $uuid]));
    }

    public function testGetOneByReturnsGroupWhenFound(): void
    {
        $group = $this->repository->create();
        $this->repository->save($group);
        $this->entityManager->flush();

        $uuid = $group->getUuid();
        $this->assertNotNull($uuid);
        $this->entityManager->clear();

        $loaded = $this->repository->getOneBy(['uuid' => $uuid]);
        $this->assertSame($uuid, $loaded->getUuid());
    }

    public function testGetOneByThrowsNotFoundExceptionForUnknownUuid(): void
    {
        $this->expectException(AttributeGroupNotFoundException::class);

        $this->repository->getOneBy(['uuid' => '00000000-0000-0000-0000-000000000000']);
    }

    public function testCountByReturnsZeroForEmptyGroup(): void
    {
        $group = $this->repository->create();
        $this->repository->save($group);
        $this->entityManager->flush();

        $this->assertSame(0, $this->attributeRepository->countBy(['group' => $group]));
    }

    public function testCountByCountsLinkedAttributes(): void
    {
        $group = $this->repository->create();
        $this->repository->save($group);

        foreach (['count-attr-a', 'count-attr-b'] as $i => $key) {
            $attribute = $this->attributeRepository->create($group);
            $attribute->setKey($key);
            $attribute->setType(AttributeInterface::TYPE_TEXT);
            $this->attributeRepository->save($attribute);

            $ga = new AttributeGroupAttribute($group, $attribute);
            $ga->setPosition($i);
            $group->addGroupAttribute($ga);
        }

        $this->repository->save($group);
        $this->entityManager->flush();

        $this->assertSame(2, $this->attributeRepository->countBy(['group' => $group]));
    }

    private function createGroupWithTranslatedAttribute(): void
    {
        $group = $this->repository->create();
        $group->setDefaultLocale('de');
        $group->addTranslation(new AttributeGroupTranslation($group, 'de', 'Farbe & Form'));
        $this->repository->save($group);

        $attribute = $this->attributeRepository->create($group);
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $attribute->setDefaultLocale('de');
        $attribute->addTranslation(new AttributeTranslation($attribute, 'de', 'Farbe'));
        $this->attributeRepository->save($attribute);

        $group->addGroupAttribute(new AttributeGroupAttribute($group, $attribute));
        $this->repository->save($group);
        $this->entityManager->flush();
    }

    /**
     * Walks the group the way the product family form metadata visitor does.
     *
     * @return list<string>
     */
    private function walkGroup(AttributeGroupInterface $group, string $locale): array
    {
        $groupTranslation = $group->getTranslation($locale)
            ?? (($defaultLocale = $group->getDefaultLocale()) !== null ? $group->getTranslation($defaultLocale) : null);
        $names = [$groupTranslation?->getName() ?? ''];

        foreach ($group->getGroupAttributes() as $groupAttribute) {
            $attribute = $groupAttribute->getAttribute();
            $attributeTranslation = $attribute->getTranslation($locale)
                ?? (($defaultLocale = $attribute->getDefaultLocale()) !== null ? $attribute->getTranslation($defaultLocale) : null);
            $names[] = $attributeTranslation?->getName() ?? $attribute->getKey();
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
