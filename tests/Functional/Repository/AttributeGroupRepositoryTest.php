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
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\AttributeGroupRepository;
use Symfony\Component\Uid\Uuid;

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

    public function testCreateNewGeneratesUuidAndAcceptsPinnedUuid(): void
    {
        $this->assertTrue(Uuid::isValid($this->repository->createNew()->getUuid()));

        $uuid = Uuid::v7()->toRfc4122();
        $this->assertSame($uuid, $this->repository->createNew($uuid)->getUuid());
    }

    public function testFindBySortsByUuidInCreationOrder(): void
    {
        $first = $this->repository->createNew();
        $first->addTranslation(new AttributeGroupTranslation($first, 'en', 'First'));
        $second = $this->repository->createNew();
        $second->addTranslation(new AttributeGroupTranslation($second, 'en', 'Second'));
        $this->repository->save($second);
        $this->repository->save($first);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $uuids = \array_map(
            static fn (AttributeGroupInterface $group): string => $group->getUuid(),
            [...$this->repository->findBy([], ['uuid' => 'desc'])],
        );

        $this->assertSame([$second->getUuid(), $first->getUuid()], $uuids);
    }

    public function testCreateGeneratesUniqueUuids(): void
    {
        $a = $this->repository->createNew();
        $b = $this->repository->createNew();
        $this->assertNotSame($a->getUuid(), $b->getUuid());
    }

    public function testFindByWithoutFiltersReturnsAllGroups(): void
    {
        $this->assertSame([], [...$this->repository->findBy()]);

        $a = $this->repository->createNew();
        $b = $this->repository->createNew();
        $this->repository->save($a);
        $this->repository->save($b);
        $this->entityManager->flush();

        $this->assertCount(2, [...$this->repository->findBy()]);
    }

    public function testFindByFiltersAndSorts(): void
    {
        $a = $this->repository->createNew();
        $a->setExternalIdentifier('group-a');
        $b = $this->repository->createNew();
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

        $byUuid = [...$this->repository->findBy([], ['uuid' => 'desc'])];
        $this->assertSame([$b->getUuid(), $a->getUuid()], [$byUuid[0]->getUuid(), $byUuid[1]->getUuid()]);
    }

    public function testFindByIgnoresUnknownSortFields(): void
    {
        $group = $this->repository->createNew();
        $this->repository->save($group);
        $this->entityManager->flush();

        $this->assertCount(1, [...$this->repository->findBy([], ['name; DROP' => 'desc'])]);
    }

    public function testFindByWithGroupTranslationsSelectPreloadsTranslations(): void
    {
        $group = $this->repository->createNew();
        $group->addTranslation(new AttributeGroupTranslation($group, 'en', 'My Group'));
        $group->addTranslation(new AttributeGroupTranslation($group, 'de', 'Meine Gruppe'));
        $this->repository->save($group);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = [...$this->repository->findBy(
            [],
            [],
            [AttributeGroupRepositoryInterface::SELECT_GROUP_TRANSLATIONS => true],
        )][0];

        $queriesBefore = $this->countQueries();
        $this->assertSame('Meine Gruppe', $loaded->getTranslation('de')?->getName());
        $this->assertSame('My Group', $loaded->getTranslation('en')?->getName());
        $this->assertSame($queriesBefore, $this->countQueries(), 'the translations come preloaded with the group');
    }

    public function testSavePersistsAndCanBeFoundByUuid(): void
    {
        $group = $this->repository->createNew();
        $this->repository->save($group);
        $this->entityManager->flush();

        $uuid = $group->getUuid();
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertInstanceOf(AttributeGroupInterface::class, $loaded);
        $this->assertSame($uuid, $loaded->getUuid());
    }

    public function testFindOneByExternalIdentifierReturnsGroup(): void
    {
        $group = $this->repository->createNew();
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
        $group = $this->repository->createNew();
        $translation = new AttributeGroupTranslation($group, 'en', 'My Group');
        $translation->setDescription('A description');
        $group->addTranslation($translation);
        $this->repository->save($group);
        $this->entityManager->flush();

        $uuid = $group->getUuid();
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
        $group = $this->repository->createNew();
        $this->repository->save($group);
        $this->entityManager->flush();

        $uuid = $group->getUuid();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertNotNull($loaded);

        $this->repository->remove($loaded);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->repository->findOneBy(['uuid' => $uuid]));
    }

    public function testGetOneByReturnsGroupWhenFound(): void
    {
        $group = $this->repository->createNew();
        $this->repository->save($group);
        $this->entityManager->flush();

        $uuid = $group->getUuid();
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
        $group = $this->repository->createNew();
        $this->repository->save($group);
        $this->entityManager->flush();

        $this->assertSame(0, $this->attributeRepository->countBy(['group' => $group]));
    }

    public function testCountByCountsLinkedAttributes(): void
    {
        $group = $this->repository->createNew();
        $this->repository->save($group);

        foreach (['count-attr-a', 'count-attr-b'] as $key) {
            $attribute = $this->attributeRepository->createNew($group);
            $attribute->setKey($key);
            $attribute->setType(AttributeInterface::TYPE_TEXT);
            $this->attributeRepository->save($attribute);
        }

        $this->entityManager->flush();

        $this->assertSame(2, $this->attributeRepository->countBy(['group' => $group]));
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
