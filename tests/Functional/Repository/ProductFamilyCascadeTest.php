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

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class ProductFamilyCascadeTest extends SuluTestCase
{
    private AttributeGroupRepositoryInterface $groupRepository;

    private AttributeRepositoryInterface $attributeRepository;

    private ProductFamilyRepositoryInterface $familyRepository;

    private ProductRepositoryInterface $productRepository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        $this->groupRepository = $groupRepository;

        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        $this->attributeRepository = $attributeRepository;

        /** @var ProductFamilyRepositoryInterface $familyRepository */
        $familyRepository = $container->get(ProductFamilyRepositoryInterface::class);
        $this->familyRepository = $familyRepository;

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get(ProductRepositoryInterface::class);
        $this->productRepository = $productRepository;

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

    private function fetchCount(string $sql): int
    {
        $count = $this->entityManager->getConnection()->fetchOne($sql);
        \assert(\is_numeric($count));

        return (int) $count;
    }

    private function countProductValues(): int
    {
        return $this->fetchCount('SELECT COUNT(*) FROM pr_product_attribute_values');
    }

    /**
     * @return array{family: ProductFamilyInterface, familyAttribute: ProductFamilyAttributeInterface}
     */
    private function createFixture(): array
    {
        $group = $this->groupRepository->create();
        $this->groupRepository->save($group);

        $attribute = $this->attributeRepository->create($group);
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $this->attributeRepository->save($attribute);

        $family = $this->familyRepository->create();
        $familyAttribute = new ProductFamilyAttribute($family, $attribute);
        $family->addFamilyAttribute($familyAttribute);
        $this->familyRepository->save($family);

        $product = $this->productRepository->createNew($family);

        $value = new ProductAttributeValue($product, $attribute, 'color');
        $value->setText('red');
        $value->setProductFamilyAttribute($familyAttribute);
        $product->addAttribute($value);

        $this->productRepository->add($product);
        $this->entityManager->flush();

        return ['family' => $family, 'familyAttribute' => $familyAttribute];
    }

    public function testRemovingFamilyAttributeCascadesToProductValues(): void
    {
        $fixture = $this->createFixture();
        $this->assertSame(1, $this->countProductValues());

        $family = $fixture['family'];
        $family->removeFamilyAttribute($fixture['familyAttribute']);
        $this->familyRepository->save($family);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertSame(0, $this->countProductValues());
    }

    public function testRemovingFamilyWithProductsIsRejectedByForeignKey(): void
    {
        $fixture = $this->createFixture();
        $uuid = $fixture['family']->getUuid();
        $this->assertNotNull($uuid);
        $this->assertSame(1, $this->countProductValues());

        // The product family FK is NOT NULL with on-delete RESTRICT, so the database
        // must refuse to remove a family while products are still assigned to it.
        $this->expectException(ForeignKeyConstraintViolationException::class);

        $this->familyRepository->remove($fixture['family']);
        $this->entityManager->flush();
    }
}
