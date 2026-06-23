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
use Sulu\Product\Domain\Exception\AttributeNotFoundException;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\AttributeRepository;

#[CoversClass(AttributeRepository::class)]
class AttributeRepositoryTest extends SuluTestCase
{
    private AttributeRepositoryInterface $repository;

    private AttributeGroupRepositoryInterface $groupRepository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var AttributeRepositoryInterface $repository */
        $repository = $container->get(AttributeRepositoryInterface::class);
        $this->repository = $repository;

        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        $this->groupRepository = $groupRepository;

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

    private function createGroup(): AttributeGroupInterface
    {
        $group = $this->groupRepository->create();
        $this->groupRepository->save($group);
        $this->entityManager->flush();

        return $group;
    }

    public function testFindOneByIdReturnsAttribute(): void
    {
        $group = $this->createGroup();
        $attribute = $this->repository->create($group);
        $attribute->setKey('id-filter');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $this->repository->save($attribute);
        $this->entityManager->flush();

        $id = $attribute->getId();
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['id' => $id]);
        $this->assertNotNull($loaded);
        $this->assertSame($id, $loaded->getId());
        $this->assertNull($this->repository->findOneBy(['id' => 0]));
    }

    public function testCreateReturnsFreshAttributeWithUuid(): void
    {
        $attribute = $this->repository->create($this->createGroup());

        $this->assertNotNull($attribute->getUuid());
        $this->assertNotSame('', $attribute->getUuid());
    }

    public function testCreateGeneratesUniqueUuidPerCall(): void
    {
        $group = $this->createGroup();
        $a = $this->repository->create($group);
        $b = $this->repository->create($group);

        $this->assertNotSame($a->getUuid(), $b->getUuid());
    }

    public function testSavePersistsAttributeAndItCanBeFoundByUuid(): void
    {
        $attribute = $this->repository->create($this->createGroup());
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_TEXT);

        $this->repository->save($attribute);
        $this->entityManager->flush();

        $uuid = $attribute->getUuid();
        $this->assertNotNull($uuid);

        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);

        $this->assertInstanceOf(AttributeInterface::class, $loaded);
        $this->assertSame($uuid, $loaded->getUuid());
        $this->assertSame('color', $loaded->getKey());
        $this->assertSame(AttributeInterface::TYPE_TEXT, $loaded->getType());
    }

    public function testFindOneByKeyReturnsAttribute(): void
    {
        $attribute = $this->repository->create($this->createGroup());
        $attribute->setKey('size');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);

        $this->repository->save($attribute);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['key' => 'size']);

        $this->assertInstanceOf(AttributeInterface::class, $loaded);
        $this->assertSame('size', $loaded->getKey());
    }

    public function testFindOneByExternalIdentifierReturnsAttribute(): void
    {
        $attribute = $this->repository->create($this->createGroup());
        $attribute->setKey('weight');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attribute->setExternalIdentifier('ext-attribute-1');

        $this->repository->save($attribute);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['externalIdentifier' => 'ext-attribute-1']);

        $this->assertInstanceOf(AttributeInterface::class, $loaded);
        $this->assertSame('weight', $loaded->getKey());
        $this->assertSame('ext-attribute-1', $loaded->getExternalIdentifier());
    }

    public function testFindOneByIgnoresUnsupportedFilters(): void
    {
        $attribute = $this->repository->create($this->createGroup());
        $attribute->setKey('length');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);

        $this->repository->save($attribute);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy([
            'key' => 'length',
            'type' => AttributeInterface::TYPE_TEXT,
        ]);

        $this->assertInstanceOf(AttributeInterface::class, $loaded);
        $this->assertSame('length', $loaded->getKey());
        $this->assertSame(AttributeInterface::TYPE_NUMBER, $loaded->getType());
    }

    public function testGetOneByKeyReturnsAttribute(): void
    {
        $attribute = $this->repository->create($this->createGroup());
        $attribute->setKey('material');
        $attribute->setType(AttributeInterface::TYPE_TEXT);

        $this->repository->save($attribute);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->repository->getOneBy(['key' => 'material']);

        $this->assertSame('material', $loaded->getKey());
    }

    public function testGetOneByThrowsWhenNoMatch(): void
    {
        $this->expectException(AttributeNotFoundException::class);

        $this->repository->getOneBy(['key' => 'does-not-exist']);
    }

    public function testFindOneByReturnsNullWhenNoMatch(): void
    {
        $loaded = $this->repository->findOneBy(['key' => 'does-not-exist']);

        $this->assertNull($loaded);
    }

    public function testFindOneByReturnsNullForUnknownUuid(): void
    {
        $loaded = $this->repository->findOneBy(['uuid' => '00000000-0000-0000-0000-000000000000']);

        $this->assertNull($loaded);
    }

    public function testFindOneByLoadsTranslationViaCurrentLocale(): void
    {
        $attribute = $this->repository->create($this->createGroup());
        $attribute->setKey('material');
        $attribute->setType(AttributeInterface::TYPE_TEXT);

        $translation = new AttributeTranslation($attribute, 'en', 'Material');
        $translation->setDescription('What it is made of');
        $attribute->addTranslation($translation);

        $this->repository->save($attribute);
        $this->entityManager->flush();

        $uuid = $attribute->getUuid();
        $this->assertNotNull($uuid);

        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);

        $this->assertInstanceOf(AttributeInterface::class, $loaded);

        $loadedTranslation = $loaded->getTranslation('en');
        $this->assertNotNull($loadedTranslation);
        $this->assertSame('en', $loadedTranslation->getLocale());
        $this->assertSame('Material', $loadedTranslation->getName());
        $this->assertSame('What it is made of', $loadedTranslation->getDescription());
    }

    public function testFindOneByExplicitLocaleReturnsCorrectTranslation(): void
    {
        $attribute = $this->repository->create($this->createGroup());
        $attribute->setKey('weight');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);

        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', 'Weight'));
        $attribute->addTranslation(new AttributeTranslation($attribute, 'de', 'Gewicht'));

        $this->repository->save($attribute);
        $this->entityManager->flush();

        $uuid = $attribute->getUuid();
        $this->assertNotNull($uuid);

        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertInstanceOf(AttributeInterface::class, $loaded);

        $en = $loaded->getTranslation('en');
        $de = $loaded->getTranslation('de');
        $fr = $loaded->getTranslation('fr');

        $this->assertNotNull($en);
        $this->assertSame('Weight', $en->getName());
        $this->assertNotNull($de);
        $this->assertSame('Gewicht', $de->getName());
        $this->assertNull($fr);
    }

    public function testRemoveDeletesAttributeFromDatabase(): void
    {
        $attribute = $this->repository->create($this->createGroup());
        $attribute->setKey('to-remove');
        $attribute->setType(AttributeInterface::TYPE_TEXT);

        $this->repository->save($attribute);
        $this->entityManager->flush();

        $uuid = $attribute->getUuid();
        $this->assertNotNull($uuid);

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertInstanceOf(AttributeInterface::class, $loaded);

        $this->repository->remove($loaded);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->repository->findOneBy(['uuid' => $uuid]));
    }

    public function testFindNextPositionInGroupReturnsZeroWhenGroupIsEmpty(): void
    {
        $group = $this->createGroup();

        $this->assertSame(0, $this->repository->findNextPositionInGroup($group));
    }

    public function testFindNextPositionInGroupReturnsMaxPositionPlusOne(): void
    {
        $group = $this->createGroup();

        foreach ([0, 1, 5] as $i => $pos) {
            $a = $this->repository->create($group);
            $a->setKey('pos-attr-' . $i);
            $a->setPosition($pos);
            $this->repository->save($a);
        }
        $this->entityManager->flush();

        $this->assertSame(6, $this->repository->findNextPositionInGroup($group));
    }

    public function testFindByGroupWithPositionAtLeastReturnsMatchingAttributes(): void
    {
        $group = $this->createGroup();

        foreach ([0, 1, 2] as $i => $pos) {
            $a = $this->repository->create($group);
            $a->setKey('atleast-' . $i);
            $a->setPosition($pos);
            $this->repository->save($a);
        }
        $this->entityManager->flush();

        $results = $this->repository->findByGroupWithPositionAtLeast($group, 1);

        $this->assertCount(2, $results);
        $positions = \array_map(fn (AttributeInterface $a) => $a->getPosition(), $results);
        \sort($positions);
        $this->assertSame([1, 2], $positions);
    }

    public function testFindByGroupWithPositionAtLeastExcludesSpecifiedAttribute(): void
    {
        $group = $this->createGroup();

        $attrs = [];
        foreach ([0, 1, 2] as $i => $pos) {
            $a = $this->repository->create($group);
            $a->setKey('atleast-excl-' . $i);
            $a->setPosition($pos);
            $this->repository->save($a);
            $attrs[] = $a;
        }
        $this->entityManager->flush();

        $results = $this->repository->findByGroupWithPositionAtLeast($group, 0, $attrs[0]);

        $this->assertCount(2, $results);
        $uuids = \array_map(fn (AttributeInterface $a) => $a->getUuid(), $results);
        $this->assertNotContains($attrs[0]->getUuid(), $uuids);
    }

    public function testFindByGroupWithPositionBetweenReturnsAttrsInRange(): void
    {
        $group = $this->createGroup();

        foreach ([0, 1, 2, 3] as $i => $pos) {
            $a = $this->repository->create($group);
            $a->setKey('between-' . $i);
            $a->setPosition($pos);
            $this->repository->save($a);
        }
        $this->entityManager->flush();

        $results = $this->repository->findByGroupWithPositionBetween($group, 1, 2);

        $this->assertCount(2, $results);
        $positions = \array_map(fn (AttributeInterface $a) => $a->getPosition(), $results);
        \sort($positions);
        $this->assertSame([1, 2], $positions);
    }

    public function testFindByGroupWithPositionBetweenExcludesSpecifiedAttribute(): void
    {
        $group = $this->createGroup();

        $attrs = [];
        foreach ([0, 1, 2] as $i => $pos) {
            $a = $this->repository->create($group);
            $a->setKey('between-excl-' . $i);
            $a->setPosition($pos);
            $this->repository->save($a);
            $attrs[] = $a;
        }
        $this->entityManager->flush();

        $results = $this->repository->findByGroupWithPositionBetween($group, 0, 2, $attrs[0]);

        $this->assertCount(2, $results);
        $uuids = \array_map(fn (AttributeInterface $a) => $a->getUuid(), $results);
        $this->assertNotContains($attrs[0]->getUuid(), $uuids);
    }

    public function testCountByReturnsZeroForEmptyGroup(): void
    {
        $group = $this->createGroup();

        $this->assertSame(0, $this->repository->countBy(['group' => $group]));
    }

    public function testCountByCountsAttributesInGroup(): void
    {
        $group = $this->createGroup();
        $other = $this->createGroup();

        foreach (['count-a', 'count-b'] as $key) {
            $a = $this->repository->create($group);
            $a->setKey($key);
            $this->repository->save($a);
        }

        $outsider = $this->repository->create($other);
        $outsider->setKey('count-other');
        $this->repository->save($outsider);

        $this->entityManager->flush();

        $this->assertSame(2, $this->repository->countBy(['group' => $group]));
        $this->assertSame(1, $this->repository->countBy(['group' => $other]));
    }
}
