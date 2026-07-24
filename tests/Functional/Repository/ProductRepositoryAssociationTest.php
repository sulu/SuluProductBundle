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
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\ProductRepository;

#[CoversClass(ProductRepository::class)]
class ProductRepositoryAssociationTest extends SuluTestCase
{
    private ProductRepositoryInterface $repository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ProductRepositoryInterface $repository */
        $repository = $container->get(ProductRepositoryInterface::class);
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

    private function createLiveProduct(): ProductInterface
    {
        $product = $this->repository->createNew();
        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage(DimensionContentInterface::STAGE_LIVE);
        $dimensionContent->setTemplateKey('product');
        $product->addDimensionContent($dimensionContent);

        $this->repository->add($product);
        $this->entityManager->persist($dimensionContent);

        return $product;
    }

    private function getLiveDimensionContent(ProductInterface $product): ProductDimensionContentInterface
    {
        foreach ($product->getDimensionContents() as $dimensionContent) {
            if ('en' === $dimensionContent->getLocale() && DimensionContentInterface::STAGE_LIVE === $dimensionContent->getStage()) {
                return $dimensionContent;
            }
        }

        throw new \RuntimeException('Live "en" dimension content not found.');
    }

    public function testFindByAssociationTargetUuidAndTypeReturnsOnlyMatchingReferrers(): void
    {
        $target = $this->createLiveProduct();
        $productA = $this->createLiveProduct();
        $productD = $this->createLiveProduct();
        $productE = $this->createLiveProduct();

        $dimensionContentA = $this->getLiveDimensionContent($productA);
        $dimensionContentA->addAssociation(new ProductAssociation($dimensionContentA, $target, 'alternative'));

        $dimensionContentD = $this->getLiveDimensionContent($productD);
        $dimensionContentD->addAssociation(new ProductAssociation($dimensionContentD, $target, 'alternative'));

        $this->entityManager->flush();
        $this->entityManager->clear();

        $products = \iterator_to_array(
            $this->repository->findBy([
                'locale' => 'en',
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'associationTargetUuid' => $target->getUuid(),
                'associationType' => 'alternative',
            ]),
            false,
        );

        $uuids = \array_map(static fn (ProductInterface $p) => $p->getUuid(), $products);
        \sort($uuids);
        $expected = [$productA->getUuid(), $productD->getUuid()];
        \sort($expected);

        $this->assertSame($expected, $uuids);
        $this->assertNotContains($productE->getUuid(), $uuids);
        $this->assertNotContains($target->getUuid(), $uuids);
    }

    public function testFindByAssociationTargetUuidWithoutTypeReturnsAllInboundReferrersRegardlessOfType(): void
    {
        $target = $this->createLiveProduct();
        $productA = $this->createLiveProduct();
        $productD = $this->createLiveProduct();
        $productE = $this->createLiveProduct();

        $dimensionContentA = $this->getLiveDimensionContent($productA);
        $dimensionContentA->addAssociation(new ProductAssociation($dimensionContentA, $target, 'alternative'));

        $dimensionContentD = $this->getLiveDimensionContent($productD);
        $dimensionContentD->addAssociation(new ProductAssociation($dimensionContentD, $target, 'accessory'));

        $this->entityManager->flush();
        $this->entityManager->clear();

        $products = \iterator_to_array(
            $this->repository->findBy([
                'locale' => 'en',
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'associationTargetUuid' => $target->getUuid(),
            ]),
            false,
        );

        $uuids = \array_map(static fn (ProductInterface $p) => $p->getUuid(), $products);
        \sort($uuids);
        $expected = [$productA->getUuid(), $productD->getUuid()];
        \sort($expected);

        $this->assertSame($expected, $uuids);
        $this->assertNotContains($productE->getUuid(), $uuids);
    }

    public function testFindByAssociationTargetUuidWithoutLocaleAndStageThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        \iterator_to_array(
            $this->repository->findBy([
                'associationTargetUuid' => '00000000-0000-0000-0000-000000000000',
            ]),
            false,
        );
    }
}
