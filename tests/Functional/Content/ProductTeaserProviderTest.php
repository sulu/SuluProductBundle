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

namespace Sulu\Product\Tests\Functional\Content;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductTeaserProvider;
use Sulu\Route\Domain\Model\Route;

#[CoversClass(ProductTeaserProvider::class)]
class ProductTeaserProviderTest extends SuluTestCase
{
    private EntityManagerInterface $entityManager;

    private ProductRepositoryInterface $productRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = self::getContainer()->get('sulu_product.product_repository');
        $this->productRepository = $productRepository;

        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    /** A variant has no content of its own, so its teaser carries its own title and URL only. */
    public function testVariantTeasersCarryTheirOwnFields(): void
    {
        $parent = $this->createParent();
        $black = $this->createVariant($parent, 'NC3FX black', '/products/nc3fx-black');
        $white = $this->createVariant($parent, 'NC3FX white', '/products/nc3fx-white');
        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var ProductTeaserProvider $teaserProvider */
        $teaserProvider = self::getContainer()->get('sulu_product.product_teaser_provider');
        $teasers = $teaserProvider->find([$white->getUuid(), $black->getUuid()], 'de');

        self::assertCount(2, $teasers);
        self::assertSame($white->getUuid(), $teasers[0]->getId());
        self::assertSame('NC3FX white', $teasers[0]->getTitle());
        self::assertSame('/products/nc3fx-white', $teasers[0]->getUrl());
        self::assertSame('', $teasers[0]->getDescription(), 'the product\'s excerpt stays with the product');
        self::assertNull($teasers[0]->getMediaId());
        self::assertSame('NC3FX black', $teasers[1]->getTitle());
        self::assertSame('/products/nc3fx-black', $teasers[1]->getUrl());
    }

    private function createParent(): ProductInterface
    {
        $parent = $this->productRepository->createNew();
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $parentContent = $parent->createDimensionContent();
        $parentContent->setLocale('de');
        $parentContent->setStage(DimensionContentInterface::STAGE_LIVE);
        $parentContent->setTemplateKey('product');
        $parentContent->setTemplateData(['title' => 'NC3FX']);
        $parentContent->setExcerptData(['description' => 'Three-pole XLR connector']);
        $parent->addDimensionContent($parentContent);

        $this->productRepository->add($parent);
        $this->entityManager->persist($parentContent);

        return $parent;
    }

    private function createVariant(ProductInterface $parent, string $title, string $slug): ProductInterface
    {
        $variant = $this->productRepository->createNew();
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);

        $variantContent = $variant->createDimensionContent();
        $variantContent->setLocale('de');
        $variantContent->setStage(DimensionContentInterface::STAGE_LIVE);
        $variantContent->setTemplateKey('product');
        $variantContent->setTemplateData(['title' => $title]);

        // The route association carries no cascade, so it is persisted on its own.
        $route = new Route(ProductInterface::RESOURCE_KEY, $variant->getUuid(), 'de', $slug);
        $variantContent->setRoute($route);
        $this->entityManager->persist($route);

        $variant->addDimensionContent($variantContent);

        $this->productRepository->add($variant);
        $this->entityManager->persist($variantContent);

        return $variant;
    }
}
