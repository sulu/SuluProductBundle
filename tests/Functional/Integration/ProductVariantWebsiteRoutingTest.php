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

namespace Sulu\Product\Tests\Functional\Integration;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Route\Domain\Model\Route;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
class ProductVariantWebsiteRoutingTest extends SuluTestCase
{
    private EntityManagerInterface $entityManager;

    private ProductRepositoryInterface $productRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get('sulu_product.product_repository');
        $this->productRepository = $productRepository;

        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testAVariantOfALiveProductIsReachable(): void
    {
        $parent = $this->createParent(DimensionContentInterface::STAGE_LIVE);
        $this->createVariant($parent, '/products/nl4fx-4');
        $this->entityManager->flush();

        $response = $this->requestVariant();

        $this->assertHttpStatusCode(200, $response);
        self::assertStringContainsString('NL4FX', (string) $response->getContent());
    }

    public function testAVariantOfAnUnpublishedProductIsNotFound(): void
    {
        $parent = $this->createParent(DimensionContentInterface::STAGE_DRAFT);
        $this->createVariant($parent, '/products/nl4fx-4');
        $this->entityManager->flush();

        $this->assertHttpStatusCode(404, $this->requestVariant());
    }

    private function requestVariant(): Response
    {
        self::ensureKernelShutdown();

        $websiteClient = $this->createWebsiteClient();
        $websiteClient->request('GET', 'http://sulu.io/de/products/nl4fx-4');

        return $websiteClient->getResponse();
    }

    private function createParent(string $stage): ProductInterface
    {
        $parent = $this->productRepository->createNew();
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $parentContent = $parent->createDimensionContent();
        $parentContent->setLocale('de');
        $parentContent->setStage($stage);
        $parentContent->setTemplateKey('product');
        $parentContent->setTemplateData(['title' => 'NL4FX', 'description' => 'Parent description']);
        $parent->addDimensionContent($parentContent);

        $this->productRepository->add($parent);
        $this->entityManager->persist($parentContent);

        return $parent;
    }

    private function createVariant(ProductInterface $parent, string $slug): ProductInterface
    {
        $variant = $this->productRepository->createNew();
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);

        $variantContent = $variant->createDimensionContent();
        $variantContent->setLocale('de');
        $variantContent->setStage(DimensionContentInterface::STAGE_LIVE);
        $variantContent->setTemplateKey('product');
        $variantContent->setCode('NL4FX-4');
        $variantContent->setTemplateData(['title' => 'NL4FX-4 Variant']);

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
