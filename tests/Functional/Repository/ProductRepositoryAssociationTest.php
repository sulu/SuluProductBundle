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

        $unlocalizedDimensionContent = $product->createDimensionContent();
        $unlocalizedDimensionContent->setStage(DimensionContentInterface::STAGE_LIVE);
        $product->addDimensionContent($unlocalizedDimensionContent);

        $localizedDimensionContent = $product->createDimensionContent();
        $localizedDimensionContent->setLocale('en');
        $localizedDimensionContent->setStage(DimensionContentInterface::STAGE_LIVE);
        $localizedDimensionContent->setTemplateKey('product');
        $product->addDimensionContent($localizedDimensionContent);

        $this->repository->add($product);
        $this->entityManager->persist($unlocalizedDimensionContent);
        $this->entityManager->persist($localizedDimensionContent);

        return $product;
    }

    private function getUnlocalizedDimensionContent(ProductInterface $product): ProductDimensionContentInterface
    {
        foreach ($product->getDimensionContents() as $dimensionContent) {
            if (null === $dimensionContent->getLocale() && DimensionContentInterface::STAGE_LIVE === $dimensionContent->getStage()) {
                return $dimensionContent;
            }
        }

        throw new \RuntimeException('Unlocalized live dimension content not found.');
    }

    public function testFindByAssociationTargetUuidAndTypeReturnsOnlyMatchingReferrers(): void
    {
        $target = $this->createLiveProduct();
        $productA = $this->createLiveProduct();
        $productD = $this->createLiveProduct();
        $productE = $this->createLiveProduct();

        $dimensionContentA = $this->getUnlocalizedDimensionContent($productA);
        $dimensionContentA->addAssociation(new ProductAssociation($dimensionContentA, $target, 'alternative'));

        $dimensionContentD = $this->getUnlocalizedDimensionContent($productD);
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

        $dimensionContentA = $this->getUnlocalizedDimensionContent($productA);
        $dimensionContentA->addAssociation(new ProductAssociation($dimensionContentA, $target, 'alternative'));

        $dimensionContentD = $this->getUnlocalizedDimensionContent($productD);
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

    public function testPaginatedFindByAssociationTargetUuidReachesEveryReferrer(): void
    {
        $target = $this->createLiveProduct();
        $referrers = [$this->createLiveProduct(), $this->createLiveProduct(), $this->createLiveProduct()];

        foreach ($referrers as $referrer) {
            $dimensionContent = $this->getUnlocalizedDimensionContent($referrer);
            // Two rows to the same target are legal, because the unique constraint is scoped per type.
            $dimensionContent->addAssociation(new ProductAssociation($dimensionContent, $target, 'alternative'));
            $dimensionContent->addAssociation(new ProductAssociation($dimensionContent, $target, 'accessory'));
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $filters = [
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'associationTargetUuid' => $target->getUuid(),
        ];

        $this->assertSame(3, $this->repository->countBy($filters));

        $paginated = [];
        foreach ([1, 2] as $page) {
            foreach ($this->repository->findBy($filters + ['page' => $page, 'limit' => 2]) as $product) {
                $paginated[] = $product->getUuid();
            }
        }

        $expected = \array_map(static fn (ProductInterface $p) => $p->getUuid(), $referrers);
        \sort($expected);
        \sort($paginated);

        $this->assertSame($expected, $paginated);
    }

    public function testPaginatedFindByAssociationTargetUuidWithoutReferrersReturnsNothing(): void
    {
        $target = $this->createLiveProduct();

        $this->entityManager->flush();
        $this->entityManager->clear();

        $products = \iterator_to_array(
            $this->repository->findBy([
                'locale' => 'en',
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'associationTargetUuid' => $target->getUuid(),
                'page' => 1,
                'limit' => 10,
            ]),
            false,
        );

        $this->assertSame([], $products);
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
