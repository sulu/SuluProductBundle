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
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\AttributeRepository;

#[CoversClass(AttributeRepository::class)]
class AttributeRepositoryTest extends SuluTestCase
{
    private AttributeRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var AttributeRepositoryInterface $repository */
        $repository = $container->get(AttributeRepositoryInterface::class);
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

    public function testCreateReturnsFreshAttributeWithUuid(): void
    {
        $attribute = $this->repository->create();

        $this->assertNotNull($attribute->getUuid());
        $this->assertNotSame('', $attribute->getUuid());
    }

    public function testCreateGeneratesUniqueUuidPerCall(): void
    {
        $a = $this->repository->create();
        $b = $this->repository->create();

        $this->assertNotSame($a->getUuid(), $b->getUuid());
    }

    public function testSavePersistsAttributeAndItCanBeFoundByUuid(): void
    {
        $attribute = $this->repository->create();
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
        $attribute = $this->repository->create();
        $attribute->setKey('size');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);

        $this->repository->save($attribute);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->repository->findOneBy(['key' => 'size']);

        $this->assertInstanceOf(AttributeInterface::class, $loaded);
        $this->assertSame('size', $loaded->getKey());
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
        $attribute = $this->repository->create();
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
        $loaded->setCurrentLocale('en');

        $loadedTranslation = $loaded->getTranslation();
        $this->assertNotNull($loadedTranslation);
        $this->assertSame('en', $loadedTranslation->getLocale());
        $this->assertSame('Material', $loadedTranslation->getName());
        $this->assertSame('What it is made of', $loadedTranslation->getDescription());
    }

    public function testFindOneByExplicitLocaleReturnsCorrectTranslation(): void
    {
        $attribute = $this->repository->create();
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
        $attribute = $this->repository->create();
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
}
