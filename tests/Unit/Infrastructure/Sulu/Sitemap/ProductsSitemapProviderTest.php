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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Sitemap;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\WebsiteBundle\Sitemap\SitemapUrl;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\PortalInformation;
use Sulu\Component\Webspace\Webspace;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Sitemap\ProductsSitemapProvider;

#[CoversClass(ProductsSitemapProvider::class)]
class ProductsSitemapProviderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<WebspaceManagerInterface> */
    private ObjectProphecy $webspaceManager;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    /** @var ObjectProphecy<EntityRepository<ProductInterface>> */
    private ObjectProphecy $repository;

    private ProductsSitemapProvider $provider;

    protected function setUp(): void
    {
        $this->webspaceManager = $this->prophesize(WebspaceManagerInterface::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        /** @var ObjectProphecy<EntityRepository<ProductInterface>> $repository */
        $repository = $this->prophesize(EntityRepository::class);
        $this->repository = $repository;
        $this->entityManager->getRepository(ProductInterface::class)->willReturn($this->repository->reveal());

        $this->provider = new ProductsSitemapProvider(
            $this->entityManager->reveal(),
            $this->webspaceManager->reveal(),
            'prod',
        );
    }

    public function testGetAlias(): void
    {
        $this->assertSame('products', $this->provider->getAlias());
    }

    public function testBuildReturnsEmptyWhenNoPortalInformations(): void
    {
        $this->webspaceManager->findPortalInformationsByHostIncludingSubdomains(
            'example.org',
            'prod',
        )->willReturn([]);

        $result = $this->provider->build(1, 'https', 'example.org');

        $this->assertSame([], $result);
    }

    public function testBuildSkipsPortalsWithoutLocalization(): void
    {
        $portalInformation = $this->prophesize(PortalInformation::class);
        $portalInformation->getLocalization()->willReturn(null);

        $this->webspaceManager->findPortalInformationsByHostIncludingSubdomains(
            Argument::any(),
            'prod',
        )->willReturn([$portalInformation->reveal()]);

        $result = $this->provider->build(1, 'https', 'example.org');

        $this->assertSame([], $result);
    }

    public function testBuildReturnsEmptyWhenProductsEmpty(): void
    {
        [$portalInformation] = $this->createPortalInformation('en', 'sulu-io');

        $this->webspaceManager->findPortalInformationsByHostIncludingSubdomains(
            'example.org',
            'prod',
        )->willReturn([$portalInformation->reveal()]);

        $this->mockQueryBuilders([], []);

        $result = $this->provider->build(1, 'https', 'example.org');

        $this->assertSame([], $result);
    }

    public function testBuildSkipsProductWhenNoUrl(): void
    {
        [$portalInformation] = $this->createPortalInformation('en', 'sulu-io');

        $this->webspaceManager->findPortalInformationsByHostIncludingSubdomains(
            'example.org',
            'prod',
        )->willReturn([$portalInformation->reveal()]);

        $changed = new \DateTimeImmutable('2024-01-01');
        $product = [
            'lastModified' => null,
            'changed' => $changed,
            'locale' => 'en',
            'availableLocales' => ['en'],
            'slug' => '/products/my-product',
            'uuid' => 'product-uuid-1',
        ];

        $this->mockQueryBuilders([$product], []);

        $this->webspaceManager->findUrlByResourceLocator(
            '/products/my-product',
            'prod',
            'en',
            'sulu-io',
            'example.org',
            'https',
        )->willReturn(null);

        $result = $this->provider->build(1, 'https', 'example.org');

        $this->assertSame([], $result);
    }

    public function testBuildIncludesProductWithUrl(): void
    {
        [$portalInformation] = $this->createPortalInformation('en', 'sulu-io');

        $this->webspaceManager->findPortalInformationsByHostIncludingSubdomains(
            'example.org',
            'prod',
        )->willReturn([$portalInformation->reveal()]);

        $changed = new \DateTimeImmutable('2024-01-01');
        $product = [
            'lastModified' => null,
            'changed' => $changed,
            'locale' => 'en',
            'availableLocales' => ['en'],
            'slug' => '/products/my-product',
            'uuid' => 'product-uuid-1',
        ];

        $this->mockQueryBuilders([$product], []);

        $this->webspaceManager->findUrlByResourceLocator(
            '/products/my-product',
            'prod',
            'en',
            'sulu-io',
            'example.org',
            'https',
        )->willReturn('https://example.org/products/my-product');

        $result = $this->provider->build(1, 'https', 'example.org');

        $this->assertCount(1, $result);
        /** @var mixed $sitemapUrl */
        $sitemapUrl = $result[0];
        $this->assertInstanceOf(SitemapUrl::class, $sitemapUrl);
        $this->assertSame('https://example.org/products/my-product', $result[0]->getLoc());
        $this->assertSame($changed, $result[0]->getLastmod());
    }

    public function testBuildIncludesAlternateLinks(): void
    {
        [$portalInformation] = $this->createPortalInformation('en', 'sulu-io');

        $this->webspaceManager->findPortalInformationsByHostIncludingSubdomains(
            'example.org',
            'prod',
        )->willReturn([$portalInformation->reveal()]);

        $changed = new \DateTimeImmutable('2024-01-01');
        $product = [
            'lastModified' => null,
            'changed' => $changed,
            'locale' => 'en',
            'availableLocales' => ['en', 'de'],
            'slug' => '/products/my-product',
            'uuid' => 'product-uuid-1',
        ];

        $alternateRoute = [
            'locale' => 'de',
            'slug' => '/produkte/mein-produkt',
            'uuid' => 'product-uuid-1',
        ];

        $this->mockQueryBuilders([$product], [$alternateRoute]);

        $this->webspaceManager->findUrlByResourceLocator(
            '/products/my-product',
            'prod',
            'en',
            'sulu-io',
            'example.org',
            'https',
        )->willReturn('https://example.org/products/my-product');

        $this->webspaceManager->findUrlByResourceLocator(
            '/produkte/mein-produkt',
            'prod',
            'de',
            'sulu-io',
            'example.org',
            'https',
        )->willReturn('https://example.org/produkte/mein-produkt');

        $result = $this->provider->build(1, 'https', 'example.org');

        $this->assertCount(1, $result);
        /** @var mixed $sitemapUrl */
        $sitemapUrl = $result[0];
        $this->assertInstanceOf(SitemapUrl::class, $sitemapUrl);
        $alternateLinks = $result[0]->getAlternateLinks();
        // SitemapUrl constructor auto-adds the main locale as an alternate link, so we get 2 total
        $this->assertCount(2, $alternateLinks);
        $this->assertArrayHasKey('de', $alternateLinks);
        $this->assertSame('https://example.org/produkte/mein-produkt', $alternateLinks['de']->getHref());
        $this->assertSame('de', $alternateLinks['de']->getLocale());
    }

    public function testBuildUsesLastModifiedWhenPresent(): void
    {
        [$portalInformation] = $this->createPortalInformation('en', 'sulu-io');

        $this->webspaceManager->findPortalInformationsByHostIncludingSubdomains(
            'example.org',
            'prod',
        )->willReturn([$portalInformation->reveal()]);

        $changed = new \DateTimeImmutable('2024-01-01');
        $lastModified = new \DateTimeImmutable('2024-06-15');
        $product = [
            'lastModified' => $lastModified,
            'changed' => $changed,
            'locale' => 'en',
            'availableLocales' => ['en'],
            'slug' => '/products/my-product',
            'uuid' => 'product-uuid-1',
        ];

        $this->mockQueryBuilders([$product], []);

        $this->webspaceManager->findUrlByResourceLocator(
            '/products/my-product',
            'prod',
            'en',
            'sulu-io',
            'example.org',
            'https',
        )->willReturn('https://example.org/products/my-product');

        $result = $this->provider->build(1, 'https', 'example.org');

        $this->assertCount(1, $result);
        $this->assertSame($lastModified, $result[0]->getLastmod());
    }

    public function testBuildAlternateUrlNullSkipped(): void
    {
        [$portalInformation] = $this->createPortalInformation('en', 'sulu-io');

        $this->webspaceManager->findPortalInformationsByHostIncludingSubdomains(
            'example.org',
            'prod',
        )->willReturn([$portalInformation->reveal()]);

        $changed = new \DateTimeImmutable('2024-01-01');
        $product = [
            'lastModified' => null,
            'changed' => $changed,
            'locale' => 'en',
            'availableLocales' => ['en', 'de'],
            'slug' => '/products/my-product',
            'uuid' => 'product-uuid-1',
        ];

        $alternateRoute = [
            'locale' => 'de',
            'slug' => '/produkte/mein-produkt',
            'uuid' => 'product-uuid-1',
        ];

        $this->mockQueryBuilders([$product], [$alternateRoute]);

        $this->webspaceManager->findUrlByResourceLocator(
            '/products/my-product',
            'prod',
            'en',
            'sulu-io',
            'example.org',
            'https',
        )->willReturn('https://example.org/products/my-product');

        $this->webspaceManager->findUrlByResourceLocator(
            '/produkte/mein-produkt',
            'prod',
            'de',
            'sulu-io',
            'example.org',
            'https',
        )->willReturn(null);

        $result = $this->provider->build(1, 'https', 'example.org');

        $this->assertCount(1, $result);
        /** @var mixed $sitemapUrl */
        $sitemapUrl = $result[0];
        $this->assertInstanceOf(SitemapUrl::class, $sitemapUrl);
        // SitemapUrl constructor auto-adds the main locale, so only 1 link (no 'de' added since URL was null)
        $alternateLinks = $result[0]->getAlternateLinks();
        $this->assertCount(1, $alternateLinks);
        $this->assertArrayNotHasKey('de', $alternateLinks);
    }

    /**
     * @return array{ObjectProphecy<PortalInformation>, ObjectProphecy<Webspace>}
     */
    private function createPortalInformation(string $language, string $webspaceKey): array
    {
        $localization = new Localization($language);
        $defaultLocalization = new Localization($language);

        /** @var ObjectProphecy<Webspace> $webspace */
        $webspace = $this->prophesize(Webspace::class);
        $webspace->getDefaultLocalization()->willReturn($defaultLocalization);

        /** @var ObjectProphecy<PortalInformation> $portalInformation */
        $portalInformation = $this->prophesize(PortalInformation::class);
        $portalInformation->getLocalization()->willReturn($localization);
        $portalInformation->getWebspaceKey()->willReturn($webspaceKey);
        $portalInformation->getWebspace()->willReturn($webspace->reveal());

        return [$portalInformation, $webspace];
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param array<int, array<string, mixed>> $alternateRoutes
     */
    private function mockQueryBuilders(array $products, array $alternateRoutes): void
    {
        /** @var ObjectProphecy<QueryBuilder> $productsQb */
        $productsQb = $this->prophesize(QueryBuilder::class);
        $productsQb->distinct()->willReturn($productsQb->reveal());
        $productsQb->join(Argument::cetera())->willReturn($productsQb->reveal());
        $productsQb->leftJoin(Argument::cetera())->willReturn($productsQb->reveal());
        $productsQb->andWhere(Argument::cetera())->willReturn($productsQb->reveal());
        $productsQb->setParameter(Argument::cetera())->willReturn($productsQb->reveal());
        $productsQb->select(Argument::cetera())->willReturn($productsQb->reveal());
        $productsQb->addSelect(Argument::cetera())->willReturn($productsQb->reveal());
        $productsQb->orderBy(Argument::cetera())->willReturn($productsQb->reveal());

        /** @var ObjectProphecy<Query<mixed, mixed>> $productsQuery */
        $productsQuery = $this->prophesize(Query::class);
        $productsQuery->toIterable()->willReturn($products);
        $productsQb->getQuery()->willReturn($productsQuery->reveal());

        /** @var ObjectProphecy<QueryBuilder> $alternateQb */
        $alternateQb = $this->prophesize(QueryBuilder::class);
        $alternateQb->distinct()->willReturn($alternateQb->reveal());
        $alternateQb->join(Argument::cetera())->willReturn($alternateQb->reveal());
        $alternateQb->leftJoin(Argument::cetera())->willReturn($alternateQb->reveal());
        $alternateQb->andWhere(Argument::cetera())->willReturn($alternateQb->reveal());
        $alternateQb->setParameter(Argument::cetera())->willReturn($alternateQb->reveal());
        $alternateQb->select(Argument::cetera())->willReturn($alternateQb->reveal());
        $alternateQb->addSelect(Argument::cetera())->willReturn($alternateQb->reveal());
        $alternateQb->orderBy(Argument::cetera())->willReturn($alternateQb->reveal());

        /** @var ObjectProphecy<Query<mixed, mixed>> $alternateQuery */
        $alternateQuery = $this->prophesize(Query::class);
        $alternateQuery->toIterable()->willReturn($alternateRoutes);
        $alternateQb->getQuery()->willReturn($alternateQuery->reveal());

        // The provider calls createQueryBuilder('product') twice: once for getProducts, once for getAlternateRoutes.
        $this->repository->createQueryBuilder('product')->willReturn(
            $productsQb->reveal(),
            $alternateQb->reveal(),
        );
    }
}
