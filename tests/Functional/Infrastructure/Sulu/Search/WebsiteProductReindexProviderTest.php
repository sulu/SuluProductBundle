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
use Sulu\Content\Tests\Functional\Traits\CreateMediaTrait;
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\MessageBusInterface;

class WebsiteProductReindexProviderTest extends SuluTestCase
{
    use CreateMediaTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    /**
     * The leaves are the documents: a variant stands for its parent, which gets none of its own.
     */
    public function testVariantsAreIndexedWithTheirVariantUrlAndTheParentIsNot(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $firstVariantId = $this->createVariant($parentId, 0);
        $secondVariantId = $this->createVariant($parentId, 1, 'SEARCH-SECOND-VARIANT');
        $plainId = $this->createProduct($familyId, 'Plain', ProductInterface::TYPE_PRODUCT);
        $this->publish($parentId);
        $this->publish($plainId);

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        $plain = $engine->getDocument('website', 'products__' . $plainId . '__en');
        $this->assertSame(ProductInterface::RESOURCE_KEY, $plain['resourceKey']);
        $this->assertSame('Plain', $plain['title']);
        $plainProduct = $plain['product'];
        $this->assertIsArray($plainProduct);
        $this->assertSame($familyId, $plainProduct['productFamilyId']);
        $this->assertArrayNotHasKey('code', $plainProduct, 'The code is only searchable, through the content.');
        $plainUrl = $plain['url'];
        $this->assertIsString($plainUrl);
        $this->assertStringStartsWith('/search-product-', $plainUrl);

        $firstVariant = $engine->getDocument('website', 'products__' . $firstVariantId . '__en');
        $this->assertSame($firstVariantId, $firstVariant['resourceId']);
        $this->assertSame(['sulu-io'], $firstVariant['webspaces']);
        $parentUrl = $firstVariant['url'];
        $this->assertIsString($parentUrl);
        $this->assertStringStartsWith('/search-product-', $parentUrl);
        $this->assertStringNotContainsString('?', $parentUrl, 'The first variant is what the bare parent URL shows.');

        $secondVariant = $engine->getDocument('website', 'products__' . $secondVariantId . '__en');
        $this->assertSame($parentUrl . '?variant=SEARCH-SECOND-VARIANT', $secondVariant['url']);
        $content = $secondVariant['content'];
        $this->assertIsArray($content);
        $this->assertContains('SEARCH-SECOND-VARIANT', $content, 'The code is searchable through the content.');

        $this->expectException(DocumentNotFoundException::class);
        $engine->getDocument('website', 'products__' . $parentId . '__en');
    }

    /**
     * The admin index addresses the edit view, which a variant does not own, so it keeps the
     * opposite rule: the parent is a document there and the variant is not.
     */
    public function testAdminIndexHoldsTheParentAndNotTheVariant(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId);
        $this->publish($parentId);

        /** @var MessageBusInterface $messageBus */
        $messageBus = self::getContainer()->get('sulu_message_bus');
        $messageBus->dispatch(ReindexConfig::create()->withIndex('admin'));

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        $this->assertSame($parentId, $engine->getDocument('admin', 'products__' . $parentId . '__en')['resourceId']);

        $this->expectException(DocumentNotFoundException::class);
        $engine->getDocument('admin', 'products__' . $variantId . '__en');
    }

    /**
     * Publishing reindexes only the identifiers the listener collects. A full reindex, the path
     * `cmsig:seal:reindex` takes, runs the provider's query without identifiers instead.
     */
    public function testFullReindexIndexesTheLeavesOnly(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId);
        $draftId = $this->createProduct($familyId, 'Draft', ProductInterface::TYPE_PRODUCT);
        $this->publish($parentId);

        /** @var MessageBusInterface $messageBus */
        $messageBus = self::getContainer()->get('sulu_message_bus');
        $messageBus->dispatch(ReindexConfig::create()->withIndex('website')->withDropIndex(true));

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        $this->assertSame($variantId, $engine->getDocument('website', 'products__' . $variantId . '__en')['resourceId']);

        foreach ([$parentId, $draftId] as $missingId) {
            try {
                $engine->getDocument('website', 'products__' . $missingId . '__en');
                $this->fail('A product with variants and an unpublished product get no document.');
            } catch (DocumentNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testMediaIdComesFromTheDetailsImageAndIsNotInheritedByAVariant(): void
    {
        self::purgeDatabase();
        $media = self::createMedia(self::createCollection());
        self::getEntityManager()->flush();
        $mediaId = $media->getId();

        $familyId = $this->createProductFamily();
        $plainId = $this->createProduct(
            $familyId,
            'Plain',
            ProductInterface::TYPE_PRODUCT,
            ['image' => ['id' => $mediaId]],
        );
        $parentId = $this->createProduct($familyId, 'Parent', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId);
        $this->publish($plainId);
        $this->publish($parentId);

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        // details/image is not multilingual, so it only exists on the unlocalized dimension content.
        $plain = $engine->getDocument('website', 'products__' . $plainId . '__en');
        $this->assertSame((string) $mediaId, $plain['mediaId']);

        // A variant keeps its own image; an empty one is not filled from the parent.
        $variant = $engine->getDocument('website', 'products__' . $variantId . '__en');
        $this->assertSame('', $variant['mediaId']);
    }

    public function testUnpublishedProductIsNotIndexed(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $draftId = $this->createProduct($familyId, 'Draft', ProductInterface::TYPE_PRODUCT);

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');
        $this->expectException(DocumentNotFoundException::class);
        $engine->getDocument('website', 'products__' . $draftId . '__en');
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

    private function createVariant(string $parentId, int $position = 0, ?string $code = null): string
    {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;
        $this->client->request('POST', '/admin/api/products/' . $parentId . '/variants.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'code' => $code ?? 'SEARCH-VARIANT-' . $counter,
            'title' => 'Variant ' . $counter,
            'url' => '/search-variant-' . $counter,
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
