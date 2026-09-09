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
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\MessageBusInterface;

class WebsiteProductReindexProviderTest extends SuluTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    public function testPublishedVariantIsNotIndexedForWebsiteOrAdmin(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId);
        $this->publish($parentId);

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        $parentDocument = $engine->getDocument('website', ProductInterface::RESOURCE_KEY . '__' . $parentId . '__en');
        $this->assertSame($parentId, $parentDocument['resourceId']);

        foreach (['website', 'admin'] as $index) {
            try {
                $engine->getDocument($index, ProductInterface::RESOURCE_KEY . '__' . $variantId . '__en');
                $this->fail(\sprintf('Variant must not be indexed in "%s".', $index));
            } catch (DocumentNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * Publishing a product-with-variants only dispatches a per-entity reindex message for the
     * parent (the cascade to variants applies the content workflow directly, without its own
     * domain event), so the HTTP-driven test above never actually queries the providers' own
     * type filter for the "website" index. A full, identifier-less reindex is the path that does:
     * it runs each provider's query against every live/draft dimension content row, which is
     * exactly where an unfiltered query would surface a variant.
     */
    public function testFullReindexOfBothIndexesExcludesVariants(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId);
        $this->publish($parentId);

        /** @var MessageBusInterface $messageBus */
        $messageBus = self::getContainer()->get('sulu_message_bus');
        $messageBus->dispatch(ReindexConfig::create()->withIndex('website'));
        $messageBus->dispatch(ReindexConfig::create()->withIndex('admin'));

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        $parentDocument = $engine->getDocument('website', ProductInterface::RESOURCE_KEY . '__' . $parentId . '__en');
        $this->assertSame($parentId, $parentDocument['resourceId']);

        foreach (['website', 'admin'] as $index) {
            try {
                $engine->getDocument($index, ProductInterface::RESOURCE_KEY . '__' . $variantId . '__en');
                $this->fail(\sprintf('A full reindex must not index the variant into "%s".', $index));
            } catch (DocumentNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
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

    private function createProduct(string $familyId, string $title, string $type): string
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
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }

    private function createVariant(string $parentId): string
    {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;
        $this->client->request('POST', '/admin/api/products/' . $parentId . '/variants.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'code' => 'SEARCH-VARIANT-' . $counter,
            'title' => 'Variant ' . $counter,
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
