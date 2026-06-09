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
use Sulu\Product\Domain\Model\AttributeSetInterface;
use Sulu\Product\Domain\Model\AttributeSetTranslation;
use Sulu\Product\Domain\Repository\AttributeSetRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\AttributeSetRepository;

#[CoversClass(AttributeSetRepository::class)]
class AttributeSetRepositoryTest extends SuluTestCase
{
    private AttributeSetRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var AttributeSetRepositoryInterface $repository */
        $repository = $container->get(AttributeSetRepositoryInterface::class);
        $this->repository = $repository;

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

    public function testCreateReturnsFreshAttributeSetWithUuid(): void
    {
        $set = $this->repository->create();
        $this->assertNotNull($set->getUuid());
        $this->assertNotSame('', $set->getUuid());
    }

    public function testCreateGeneratesUniqueUuids(): void
    {
        $a = $this->repository->create();
        $b = $this->repository->create();
        $this->assertNotSame($a->getUuid(), $b->getUuid());
    }

    public function testSavePersistsAndCanBeFoundByUuid(): void
    {
        $set = $this->repository->create();
        $this->repository->save($set);
        $this->entityManager->flush();

        $uuid = $set->getUuid();
        $this->assertNotNull($uuid);
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertInstanceOf(AttributeSetInterface::class, $loaded);
        $this->assertSame($uuid, $loaded->getUuid());
    }

    public function testFindOneByReturnsNullForUnknownUuid(): void
    {
        $this->assertNull($this->repository->findOneBy(['uuid' => '00000000-0000-0000-0000-000000000000']));
    }

    public function testFindOneByLoadsTranslation(): void
    {
        $set = $this->repository->create();
        $translation = new AttributeSetTranslation($set, 'en', 'My Set');
        $translation->setDescription('A description');
        $set->addTranslation($translation);
        $this->repository->save($set);
        $this->entityManager->flush();

        $uuid = $set->getUuid();
        $this->assertNotNull($uuid);
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertNotNull($loaded);
        $loaded->setCurrentLocale('en');

        $t = $loaded->getTranslation();
        $this->assertNotNull($t);
        $this->assertSame('My Set', $t->getName());
        $this->assertSame('A description', $t->getDescription());
    }

    public function testRemoveDeletesFromDatabase(): void
    {
        $set = $this->repository->create();
        $this->repository->save($set);
        $this->entityManager->flush();

        $uuid = $set->getUuid();
        $this->assertNotNull($uuid);

        $loaded = $this->repository->findOneBy(['uuid' => $uuid]);
        $this->assertNotNull($loaded);

        $this->repository->remove($loaded);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->repository->findOneBy(['uuid' => $uuid]));
    }
}
