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
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
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
        $loaded->setCurrentLocale('en');

        $t = $loaded->getTranslation();
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
}
