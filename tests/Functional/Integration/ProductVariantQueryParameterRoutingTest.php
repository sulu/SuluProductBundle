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
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductSmartContentProvider;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * `sulu_product.variants.routing: query_parameter`: the product owns the route, a variant is
 * reached via `?variant=<code>`. Runs in separate processes because a second kernel environment
 * redeclares Sulu's generated cache classes.
 */
#[CoversNothing]
#[RunTestsInSeparateProcesses]
class ProductVariantQueryParameterRoutingTest extends SuluTestCase
{
    /**
     * @return array<string, mixed>
     */
    protected static function getKernelConfiguration(): array
    {
        return ['environment' => 'test_variant_query'];
    }

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

    public function testTheProductUrlRendersTheProductAndLinksItsVariants(): void
    {
        $admin = $this->createAdminClient();
        $productId = $this->createProduct($admin, ['de' => 'T-Shirt', 'en' => 'T-Shirt EN'], ['de' => '/t-shirt', 'en' => '/t-shirt-en']);
        $this->createVariant($admin, $productId, 'Red', 'RED', ['de', 'en']);
        $this->createVariant($admin, $productId, 'Blue', 'BLUE', ['de']);

        $crawler = $this->requestWebsite('http://sulu.io/de/t-shirt');

        self::assertSame('T-Shirt', $crawler->filter('h1')->text());
        self::assertCount(0, $crawler->filter('.current-variant'));
        self::assertSame(
            ['Red de' => '/de/t-shirt?variant=RED', 'Blue de' => '/de/t-shirt?variant=BLUE'],
            $this->getVariantUrls($crawler),
        );
        self::assertSame(['de' => '/de/t-shirt', 'en' => '/en/t-shirt-en'], $this->getLanguageSwitcherUrls($crawler));
    }

    public function testTheQueryParameterRendersTheProductWithTheVariant(): void
    {
        $admin = $this->createAdminClient();
        $productId = $this->createProduct($admin, ['de' => 'T-Shirt', 'en' => 'T-Shirt EN'], ['de' => '/t-shirt', 'en' => '/t-shirt-en']);
        $this->createVariant($admin, $productId, 'Red', 'RED', ['de', 'en']);
        $this->createVariant($admin, $productId, 'Blue', 'BLUE', ['de']);

        $crawler = $this->requestWebsite('http://sulu.io/de/t-shirt?variant=RED');

        self::assertSame('T-Shirt', $crawler->filter('h1')->text());
        self::assertSame('Red de RED', $crawler->filter('.current-variant')->text());
        self::assertSame(
            ['de' => '/de/t-shirt?variant=RED', 'en' => '/en/t-shirt-en?variant=RED'],
            $this->getLanguageSwitcherUrls($crawler),
            'the language switcher follows the variant',
        );

        $crawler = $this->requestWebsite('http://sulu.io/de/t-shirt?variant=BLUE');

        self::assertSame('Blue de BLUE', $crawler->filter('.current-variant')->text());
        self::assertSame(
            ['de' => '/de/t-shirt?variant=BLUE', 'en' => '/en/t-shirt-en'],
            $this->getLanguageSwitcherUrls($crawler),
            'a locale without the variant links the product',
        );
    }

    public function testAnUnknownOrInvalidVariantRendersTheProduct(): void
    {
        $admin = $this->createAdminClient();
        $productId = $this->createProduct($admin, ['de' => 'T-Shirt'], ['de' => '/t-shirt']);
        $this->createVariant($admin, $productId, 'Red', 'RED', ['de']);

        foreach (['?variant=GREEN', '?variant[]=RED', '?variant='] as $query) {
            $crawler = $this->requestWebsite('http://sulu.io/de/t-shirt' . $query);

            self::assertSame('T-Shirt', $crawler->filter('h1')->text(), $query);
            self::assertCount(0, $crawler->filter('.current-variant'), $query);
        }
    }

    public function testAVariantOwnsNoRoute(): void
    {
        $admin = $this->createAdminClient();
        $productId = $this->createProduct($admin, ['de' => 'T-Shirt'], ['de' => '/t-shirt']);
        $variantId = $this->createVariant($admin, $productId, 'Red', 'RED', ['de'], '/t-shirt-red');

        /** @var RouteRepositoryInterface $routeRepository */
        $routeRepository = self::getContainer()->get('sulu_route.route_repository');

        self::assertFalse($routeRepository->existBy(['resourceKey' => ProductInterface::RESOURCE_KEY, 'resourceId' => $variantId]));
        self::assertTrue($routeRepository->existBy(['resourceKey' => ProductInterface::RESOURCE_KEY, 'resourceId' => $productId]));

        self::ensureKernelShutdown();
        $websiteClient = $this->createWebsiteClient();
        $websiteClient->request('GET', 'http://sulu.io/de/t-shirt-red');

        $this->assertHttpStatusCode(404, $websiteClient->getResponse());
    }

    public function testSelectionsAndSmartContentOfferTheProductInsteadOfItsVariants(): void
    {
        $admin = $this->createAdminClient();
        $productId = $this->createProduct($admin, ['de' => 'T-Shirt'], ['de' => '/t-shirt']);
        $variantId = $this->createVariant($admin, $productId, 'Red', 'RED', ['de']);

        $admin->request('GET', '/admin/api/products.json?locale=de');
        $this->assertHttpStatusCode(200, $admin->getResponse());
        /** @var array{_embedded: array{products: list<array{id: string}>}} $list */
        $list = \json_decode((string) $admin->getResponse()->getContent(), true);
        self::assertSame([$productId], \array_column($list['_embedded']['products'], 'id'));

        self::assertNotContains($variantId, \array_column($list['_embedded']['products'], 'id'));

        /** @var ProductSmartContentProvider $smartContentProvider */
        $smartContentProvider = self::getContainer()->get('sulu_product.product_smart_content_provider');
        $filters = [
            'categories' => [],
            'categoryOperator' => 'OR',
            'websiteCategories' => [],
            'websiteCategoryOperator' => 'OR',
            'tags' => [],
            'tagOperator' => 'OR',
            'websiteTags' => [],
            'websiteTagOperator' => 'OR',
            'locale' => 'de',
            'dataSource' => null,
            'limit' => null,
            'offset' => 0,
            'includeSubFolders' => false,
            'excludeDuplicates' => false,
        ];
        self::assertSame([$productId], \array_column($smartContentProvider->findFlatBy($filters, []), 'id'));
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
     * @return array<string, string|null>
     */
    private function getVariantUrls(Crawler $crawler): array
    {
        $urls = [];
        $crawler->filter('.variants a')->each(static function(Crawler $node) use (&$urls): void {
            $urls[$node->text()] = $node->attr('href');
        });

        return $urls;
    }

    /**
     * Creates and publishes a product with variants in every locale of `$titles`.
     *
     * @param array<string, string> $titles by locale
     * @param array<string, string> $urls by locale
     */
    private function createProduct(KernelBrowser $admin, array $titles, array $urls): string
    {
        $admin->request('POST', '/admin/api/product-families.json?locale=de', [], [], [], \json_encode([
            'name' => 'Shirts',
            'key' => \uniqid('shirts-'),
        ]) ?: null);
        $this->assertHttpStatusCode(201, $admin->getResponse());
        $familyId = $this->getId($admin);

        $productId = null;
        foreach ($titles as $locale => $title) {
            $data = [
                'type' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
                'productFamily' => $familyId,
                'template' => 'product',
                'title' => $title,
                'description' => $title . ' description ' . $locale,
                'url' => $urls[$locale],
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
     * Creates and publishes a variant in every locale of `$locales`. The code is written to the
     * database afterwards: core versions each publish by `time()`, and a code is unique per stage
     * and version, so publishing a coded variant twice within a second collides.
     *
     * @param string[] $locales
     */
    private function createVariant(KernelBrowser $admin, string $productId, string $name, string $code, array $locales, ?string $url = null): string
    {
        $variantId = null;
        foreach ($locales as $locale) {
            $data = ['title' => $name . ' ' . $locale, 'url' => $url];

            if (null === $variantId) {
                $admin->request('POST', '/admin/api/products/' . $productId . '/variants.json?locale=' . $locale, [], [], [], \json_encode($data) ?: null);
                $this->assertHttpStatusCode(201, $admin->getResponse());
                $variantId = $this->getId($admin);
            } else {
                $admin->request('PUT', '/admin/api/products/' . $productId . '/variants/' . $variantId . '.json?locale=' . $locale, [], [], [], \json_encode($data) ?: null);
                $this->assertHttpStatusCode(200, $admin->getResponse());
            }

            $admin->request('POST', '/admin/api/products/' . $productId . '/variants/' . $variantId . '.json?action=publish&locale=' . $locale);
            $this->assertHttpStatusCode(200, $admin->getResponse());
        }

        self::assertIsString($variantId);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->createQueryBuilder()
            ->update(ProductDimensionContent::class, 'dimensionContent')
            ->set('dimensionContent.code', ':code')
            ->where('dimensionContent.product = :product')
            ->andWhere('dimensionContent.locale IS NULL')
            ->andWhere('dimensionContent.version = 0')
            ->setParameter('code', $code)
            ->setParameter('product', $variantId)
            ->getQuery()
            ->execute();

        return $variantId;
    }

    private function getId(KernelBrowser $client): string
    {
        $data = \json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertIsString($data['id']);

        return $data['id'];
    }
}
