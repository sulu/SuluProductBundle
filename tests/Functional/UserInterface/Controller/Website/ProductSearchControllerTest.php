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

namespace Sulu\Product\Tests\Functional\UserInterface\Controller\Website;

use CmsIg\Seal\EngineInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ProductSearchControllerTest extends SuluTestCase
{
    private KernelBrowser $client;

    private ?KernelBrowser $websiteClient = null;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    public function testRendersConfiguredTemplateWithLeafHits(): void
    {
        $this->createCatalogue();

        $content = $this->search('?q=Cable');

        $this->assertStringContainsString('total=2', $content);
        $this->assertStringContainsString('query=Cable', $content);
        $this->assertStringContainsString('variantQueryParameter=variant', $content);
        $this->assertStringContainsString('<li>Cable Plain</li>', $content);
        $this->assertStringContainsString('<li>Cable Variant</li>', $content);
        $this->assertStringNotContainsString('<li>Cable Parent</li>', $content);
    }

    public function testTermRestrictsHitsAndFiltersAndFacetsApply(): void
    {
        $this->createCatalogue();

        $content = $this->search('?q=Plain');
        $this->assertStringContainsString('<li>Cable Plain</li>', $content);
        $this->assertStringNotContainsString('<li>Cable Variant</li>', $content);

        $content = $this->search('?filter[status]=available&facet[]=status');
        $this->assertStringContainsString('statusFacet={&quot;available&quot;:2}', $content);

        $content = $this->search('?filter[status]=discontinued');
        $this->assertStringContainsString('total=0', $content);
    }

    public function testPagingIsTakenFromTheRequest(): void
    {
        $this->createCatalogue();

        $first = $this->search('?limit=1&page=1');
        $this->assertStringContainsString('page=1', $first);
        $this->assertStringContainsString('limit=1', $first);
        $this->assertCount(1, $this->titles($first));

        $second = $this->search('?limit=1&page=2');
        $this->assertStringContainsString('page=2', $second);
        $this->assertCount(1, $this->titles($second));

        $this->assertEqualsCanonicalizing(
            ['Cable Plain', 'Cable Variant'],
            [...$this->titles($first), ...$this->titles($second)],
        );
    }

    /**
     * Query parameters come from the outside, so a wrong shape must not break the page.
     */
    public function testMalformedQueryParametersFallBackToTheDefaults(): void
    {
        $this->createCatalogue();

        $content = $this->search('?q[]=Cable&filter=nonsense&range=nonsense&facet=nonsense&minmax=nonsense&sort=nonsense&page=abc&limit=999999');

        $this->assertStringContainsString('query=', $content);
        $this->assertStringContainsString('page=1', $content);
        $this->assertStringContainsString('limit=100', $content);
        $this->assertStringContainsString('total=2', $content);
    }

    /**
     * `(page - 1) * limit + limit` beyond the index's result window is a search-phase exception on
     * Elasticsearch, so the page is clamped to that window.
     */
    public function testAPageBeyondTheResultWindowIsClamped(): void
    {
        $this->createCatalogue();

        $content = $this->search('?page=999999&limit=100');

        $this->assertStringContainsString('<p>page=100</p>', $content);
        $this->assertStringContainsString('<p>limit=100</p>', $content);
    }

    public function testRangeBoundsThatAreNotNumericAreIgnored(): void
    {
        $this->createCatalogue();

        $content = $this->search('?range[attr_weight][min]=abc&range[attr_weight][max]=');

        $this->assertStringContainsString('total=2', $content);
    }

    /**
     * The webspace configures the template per format, so a format it has no template for is a 404.
     */
    public function testFormatWithoutATemplateIsNotFound(): void
    {
        $this->createCatalogue();

        $client = $this->websiteClient();
        $client->request('GET', 'http://sulu.io/en/products/search.json');

        $this->assertHttpStatusCode(404, $client->getResponse());
    }

    /**
     * @return string[]
     */
    private function titles(string $content): array
    {
        \preg_match_all('#<li>(.*?)</li>#', $content, $matches);

        return $matches[1];
    }

    private function search(string $queryString): string
    {
        $client = $this->websiteClient();
        $client->request('GET', 'http://sulu.io/en/products/search' . $queryString);

        $this->assertHttpStatusCode(200, $client->getResponse());

        return (string) $client->getResponse()->getContent();
    }

    /**
     * The website context needs its own kernel, so the fixtures are written through the admin
     * client first and this client is booted afterwards. The memory adapter keeps its documents
     * in a per-process store, so they survive the reboot.
     */
    private function websiteClient(): KernelBrowser
    {
        if (null === $this->websiteClient) {
            self::ensureKernelShutdown();
            $this->websiteClient = self::createWebsiteClient();
        }

        return $this->websiteClient;
    }

    private function createCatalogue(): void
    {
        self::purgeDatabase();
        // The memory adapter keeps one store per process, so the index outlives purgeDatabase().
        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');
        $engine->dropIndex(ProductIndex::NAME);
        $engine->createIndex(ProductIndex::NAME);

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Cable Parent', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $this->createVariant($parentId, 'Cable Variant');
        $plainId = $this->createProduct($familyId, 'Cable Plain', ProductInterface::TYPE_PRODUCT);
        $this->publish($parentId);
        $this->publish($plainId);
    }

    private function publish(string $id): void
    {
        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    private function createProductFamily(): string
    {
        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Test Family',
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }

    /**
     * @param array<string, mixed> $details
     */
    private function createProduct(string $familyId, string $title, string $type, array $details = []): string
    {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;
        $this->client->request('POST', '/admin/api/products.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'title' => $title,
            'url' => '/search-product-' . $counter,
            'productFamily' => $familyId,
            'type' => $type,
            'details' => $details,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }

    private function createVariant(string $parentId, string $title): string
    {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;
        $this->client->request('POST', '/admin/api/products/' . $parentId . '/variants.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'code' => 'SEARCH-VARIANT-' . $counter,
            'title' => $title,
            'url' => '/search-variant-' . $counter,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }
}
