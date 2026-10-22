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
use Sulu\Product\Domain\Model\ProductComponent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\ProductRepository;

#[CoversClass(ProductRepository::class)]
class ProductRepositoryComponentTest extends SuluTestCase
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

    public function testFindByComponentTargetUuidReturnsOnlyProductsWithThatComponent(): void
    {
        $target = $this->createLiveProduct();
        $productA = $this->createLiveProduct();
        $productB = $this->createLiveProduct();
        $association = $this->createLiveProduct();
        $unrelated = $this->createLiveProduct();

        foreach ([$productA, $productB] as $referrer) {
            $dimensionContent = $this->getUnlocalizedDimensionContent($referrer);
            // The same product twice is legal, because rules will later attach to the rows.
            $dimensionContent->addComponent(new ProductComponent($dimensionContent, $target, 0));
            $dimensionContent->addComponent(new ProductComponent($dimensionContent, $target, 1));
        }

        $associationDimensionContent = $this->getUnlocalizedDimensionContent($association);
        $associationDimensionContent->addAssociation(new ProductAssociation($associationDimensionContent, $target, 'alternative'));

        $this->entityManager->flush();
        $this->entityManager->clear();

        $filters = [
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'componentTargetUuid' => $target->getUuid(),
        ];

        $uuids = \array_map(
            static fn (ProductInterface $p) => $p->getUuid(),
            \iterator_to_array($this->repository->findBy($filters + ['page' => 1, 'limit' => 10]), false),
        );
        \sort($uuids);
        $expected = [$productA->getUuid(), $productB->getUuid()];
        \sort($expected);

        $this->assertSame($expected, $uuids);
        $this->assertNotContains($unrelated->getUuid(), $uuids);
        $this->assertNotContains($association->getUuid(), $uuids);
        $this->assertSame(2, $this->repository->countBy($filters));
    }

    public function testFindByComponentTargetUuidWithoutLocaleAndStageThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        \iterator_to_array(
            $this->repository->findBy([
                'componentTargetUuid' => '00000000-0000-0000-0000-000000000000',
            ]),
            false,
        );
    }
}
