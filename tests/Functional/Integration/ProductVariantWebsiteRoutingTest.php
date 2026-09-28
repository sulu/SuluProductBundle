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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

#[CoversNothing]
class ProductVariantWebsiteRoutingTest extends SuluTestCase
{
    protected function setUp(): void
    {
        self::purgeDatabase();
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    /** A variant URL renders its product's content, with the variant as `currentVariant`. */
    public function testAVariantUrlRendersItsProductWithTheVariant(): void
    {
        $admin = $this->createAdminClient();
        $parentId = $this->createProduct($admin, ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, ['de' => 'NC3FX', 'en' => 'NC3FX EN']);
        $this->createVariant($admin, $parentId, 'NC3FX-B', ['de' => '/nc3fx-b', 'en' => '/nc3fx-b-en']);
        $this->createVariant($admin, $parentId, 'NC3FX-W', ['de' => '/nc3fx-w', 'en' => '/nc3fx-w-en']);

        $crawler = $this->requestWebsite('http://sulu.io/de/nc3fx-b');

        self::assertSame('NC3FX', $crawler->filter('h1')->text());
        self::assertStringContainsString('NC3FX description de', $crawler->filter('body')->text());
        self::assertSame('NC3FX-B de', $crawler->filter('.current-variant')->text());

        self::assertSame(
            ['de' => '/de/nc3fx-b', 'en' => '/en/nc3fx-b-en'],
            $this->getLanguageSwitcherUrls($crawler),
            'the language switcher follows the variant, not its product',
        );

        $crawler = $this->requestWebsite('http://sulu.io/en/nc3fx-w-en');

        self::assertSame('NC3FX EN', $crawler->filter('h1')->text());
        self::assertSame('NC3FX-W en', $crawler->filter('.current-variant')->text());
        self::assertSame(['de' => '/de/nc3fx-w', 'en' => '/en/nc3fx-w-en'], $this->getLanguageSwitcherUrls($crawler));
    }

    /** A variant published in fewer locales than its product links the others to the start page. */
    public function testAVariantUrlLinksTheStartPageWhereTheVariantIsNotPublished(): void
    {
        $admin = $this->createAdminClient();
        $parentId = $this->createProduct($admin, ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, ['de' => 'NC3FX', 'en' => 'NC3FX EN']);
        $this->createVariant($admin, $parentId, 'NC3FX-B', ['de' => '/nc3fx-b']);

        $crawler = $this->requestWebsite('http://sulu.io/de/nc3fx-b');

        self::assertSame(['de' => '/de/nc3fx-b', 'en' => '/en'], $this->getLanguageSwitcherUrls($crawler));
    }

    /** A product without variants renders itself, as before. */
    public function testAProductUrlRendersTheProduct(): void
    {
        $admin = $this->createAdminClient();
        $this->createProduct($admin, ProductInterface::TYPE_PRODUCT, ['de' => 'NL4FX', 'en' => 'NL4FX EN'], ['de' => '/nl4fx', 'en' => '/nl4fx-en']);

        $crawler = $this->requestWebsite('http://sulu.io/de/nl4fx');

        self::assertSame('NL4FX', $crawler->filter('h1')->text());
        self::assertCount(0, $crawler->filter('.current-variant'));
        self::assertSame(['de' => '/de/nl4fx', 'en' => '/en/nl4fx-en'], $this->getLanguageSwitcherUrls($crawler));
    }

    /** The workflow keeps a variant live only with its product; the page guards the state that slips through. */
    public function testAVariantOfAnUnpublishedProductIsNotFound(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = self::getContainer()->get('sulu_product.product_repository');

        $parent = $productRepository->createNew();
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $parentContent = $parent->createDimensionContent();
        $parentContent->setLocale('de');
        $parentContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $parentContent->setTemplateKey('product');
        $parentContent->setTemplateData(['title' => 'NL4FX']);
        $parent->addDimensionContent($parentContent);
        $productRepository->add($parent);
        $entityManager->persist($parentContent);

        $variant = $productRepository->createNew();
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);
        $variantContent = $variant->createDimensionContent();
        $variantContent->setLocale('de');
        $variantContent->setStage(DimensionContentInterface::STAGE_LIVE);
        $variantContent->setTemplateKey('product');
        $variantContent->setTemplateData(['title' => 'NL4FX-4 Variant']);
        // The route association carries no cascade, so it is persisted on its own.
        $route = new Route(ProductInterface::RESOURCE_KEY, $variant->getUuid(), 'de', '/products/nl4fx-4');
        $variantContent->setRoute($route);
        $entityManager->persist($route);
        $variant->addDimensionContent($variantContent);
        $productRepository->add($variant);
        $entityManager->persist($variantContent);

        $entityManager->flush();
        self::ensureKernelShutdown();

        $websiteClient = $this->createWebsiteClient();
        $websiteClient->request('GET', 'http://sulu.io/de/products/nl4fx-4');

        $this->assertHttpStatusCode(404, $websiteClient->getResponse());
    }

    private function createAdminClient(): KernelBrowser
    {
        return $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    private function requestWebsite(string $url): Crawler
    {
        self::ensureKernelShutdown();

        $websiteClient = $this->createWebsiteClient();
        $crawler = $websiteClient->request('GET', $url);

        $this->assertHttpStatusCode(200, $websiteClient->getResponse());

        return $crawler;
    }

    /**
     * @return array<string, string|null>
     */
    private function getLanguageSwitcherUrls(Crawler $crawler): array
    {
        $urls = [];
        $crawler->filter('nav[aria-label="Language switcher"] a')->each(static function(Crawler $node) use (&$urls): void {
            $urls[$node->text()] = $node->attr('href');
        });

        return $urls;
    }

    /**
     * Creates and publishes a product in every locale of `$titles`.
     *
     * @param array<string, string> $titles by locale
     * @param array<string, string> $urls by locale, a product with variants has none
     */
    private function createProduct(KernelBrowser $admin, string $type, array $titles, array $urls = []): string
    {
        $admin->request('POST', '/admin/api/product-families.json?locale=de', [], [], [], \json_encode([
            'name' => 'Connectors',
        ]) ?: null);
        $this->assertHttpStatusCode(201, $admin->getResponse());
        $familyId = $this->getId($admin);

        $productId = null;
        foreach ($titles as $locale => $title) {
            $data = [
                'type' => $type,
                'productFamily' => $familyId,
                'template' => 'product',
                'title' => $title,
                'description' => $title . ' description ' . $locale,
                'url' => $urls[$locale] ?? null,
            ];

            if (null === $productId) {
                $admin->request('POST', '/admin/api/products.json?locale=' . $locale, [], [], [], \json_encode($data) ?: null);
                $this->assertHttpStatusCode(201, $admin->getResponse());
                $productId = $this->getId($admin);
            }

            $admin->request('PUT', '/admin/api/products/' . $productId . '.json?action=publish&locale=' . $locale, [], [], [], \json_encode($data) ?: null);
            $this->assertHttpStatusCode(200, $admin->getResponse());
        }

        self::assertIsString($productId);

        return $productId;
    }

    /**
     * Creates and publishes a variant in every locale of `$urls`. It carries no code: core versions
     * each publish by `time()`, and a code is unique per stage and version.
     *
     * @param array<string, string> $urls by locale
     */
    private function createVariant(KernelBrowser $admin, string $parentId, string $name, array $urls): void
    {
        $variantId = null;
        foreach ($urls as $locale => $url) {
            $data = ['title' => $name . ' ' . $locale, 'url' => $url];

            if (null === $variantId) {
                $admin->request('POST', '/admin/api/products/' . $parentId . '/variants.json?locale=' . $locale, [], [], [], \json_encode($data) ?: null);
                $this->assertHttpStatusCode(201, $admin->getResponse());
                $variantId = $this->getId($admin);
            } else {
                $admin->request('PUT', '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=' . $locale, [], [], [], \json_encode($data) ?: null);
                $this->assertHttpStatusCode(200, $admin->getResponse());
            }

            $admin->request('POST', '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?action=publish&locale=' . $locale);
            $this->assertHttpStatusCode(200, $admin->getResponse());
        }
    }

    private function getId(KernelBrowser $client): string
    {
        $data = \json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertIsString($data['id']);

        return $data['id'];
    }
}
