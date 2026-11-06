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

namespace Sulu\Product\Tests\Functional\Infrastructure\Sulu\Search;

use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Exception\DocumentNotFoundException;
use CmsIg\Seal\Reindex\ReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * `sulu_product.variants.routing: per_product`: a variant document links to the route of its
 * product plus `?variant=<code>`. Runs in separate processes because a second kernel environment
 * redeclares Sulu's generated cache classes.
 */
#[CoversNothing]
#[RunTestsInSeparateProcesses]
class WebsiteProductReindexPerProductTest extends SuluTestCase
{
    private KernelBrowser $client;

    /**
     * @return array<string, mixed>
     */
    protected static function getKernelConfiguration(): array
    {
        return ['environment' => 'test_variant_per_product'];
    }

    protected function setUp(): void
    {
        self::purgeDatabase();
        self::ensureKernelShutdown();
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testVariantsAreIndexedWithTheUrlOfTheirProductAndTheVariantCode(): void
    {
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, '/shirt');
        $firstVariantId = $this->createVariant($parentId, 'RED 1', 0);
        $secondVariantId = $this->createVariant($parentId, 'BLUE', 1);
        $plainId = $this->createProduct($familyId, 'Plain', ProductInterface::TYPE_PRODUCT, '/plain');
        $this->publish($parentId);
        $this->publishVariant($parentId, $firstVariantId);
        $this->publishVariant($parentId, $secondVariantId);
        $this->publish($plainId);

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        $this->assertSame('/shirt?variant=RED%201', $engine->getDocument('website', 'products__' . $firstVariantId . '__en')['url']);
        $this->assertSame('/shirt?variant=BLUE', $engine->getDocument('website', 'products__' . $secondVariantId . '__en')['url']);
        $this->assertSame('/plain', $engine->getDocument('website', 'products__' . $plainId . '__en')['url']);

        $this->expectException(DocumentNotFoundException::class);
        $engine->getDocument('website', 'products__' . $parentId . '__en');
    }

    /**
     * The route of the product is joined from its live content only: another dimension of the
     * product would yield a second document for the same variant.
     */
    public function testEachVariantIsProvidedOnce(): void
    {
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, '/shirt');
        $variantId = $this->createVariant($parentId, 'RED', 0);
        $this->publish($parentId);
        $this->publishVariant($parentId, $variantId);
        \sleep(1);
        $this->publish($parentId);

        /** @var ReindexProviderInterface $provider */
        $provider = self::getContainer()->get('sulu_product.website_product_reindex_provider');

        $urls = [];
        foreach ($provider->provide(new ReindexConfig()) as $document) {
            $urls[] = $document['url'];
        }

        $this->assertSame(['/shirt?variant=RED'], $urls);
    }

    private function publish(string $id): void
    {
        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    private function publishVariant(string $parentId, string $variantId): void
    {
        $this->client->request('POST', '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    private function createProductFamily(): string
    {
        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Test Family',
            'key' => 'test-family',
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
    private function createProduct(string $familyId, string $title, string $type, string $url, array $details = []): string
    {
        $this->client->request('POST', '/admin/api/products.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'title' => $title,
            'url' => $url,
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

    private function createVariant(string $parentId, string $code, int $position): string
    {
        $this->client->request('POST', '/admin/api/products/' . $parentId . '/variants.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'code' => $code,
            'title' => 'Variant ' . $code,
            'position' => $position,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }
}
