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
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\ProductFamilyRepository;

#[CoversClass(ProductFamilyRepository::class)]
class ProductFamilyRepositoryTest extends SuluTestCase
{
    private ProductFamilyRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ProductFamilyRepositoryInterface $repository */
        $repository = $container->get(ProductFamilyRepositoryInterface::class);
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
}
