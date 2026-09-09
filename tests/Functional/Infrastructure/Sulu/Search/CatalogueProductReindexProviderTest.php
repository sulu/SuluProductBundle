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
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\MessageBusInterface;

class CatalogueProductReindexProviderTest extends SuluTestCase
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

    public function testProductAndVariantAreBothIndexedWithTypeAndParent(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId);
        $plainId = $this->createProduct($familyId, 'Plain', ProductInterface::TYPE_PRODUCT);
        $this->publish($parentId);
        $this->publish($plainId);

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        $parent = $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($parentId, 'en'));
        $this->assertSame(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, $parent['type']);
        $this->assertSame('', $parent['parentId']);
        $this->assertSame('Parent', $parent['title']);
        $this->assertSame($familyId, $parent['productFamilyId']);
        $this->assertSame('Test Family', $parent['productFamilyName']);
        $this->assertSame('available', $parent['status']);
        $this->assertSame(['sulu-io'], $parent['webspaces']);
        $parentUrl = $parent['url'];
        $this->assertIsString($parentUrl);
        $this->assertStringStartsWith('/search-product-', $parentUrl);

        $variant = $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($variantId, 'en'));
        $this->assertSame(ProductInterface::TYPE_VARIANT, $variant['type']);
        $this->assertSame($parentId, $variant['parentId']);
        $this->assertSame($parentUrl, $variant['url']);
        $variantCode = $variant['code'];
        $this->assertIsString($variantCode);
        $this->assertStringStartsWith('SEARCH-VARIANT-', $variantCode);
        $this->assertSame(['sulu-io'], $variant['webspaces']);

        $plain = $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($plainId, 'en'));
        $this->assertSame(ProductInterface::TYPE_PRODUCT, $plain['type']);
    }

    /**
     * Publishing reindexes only the identifiers the listener collects. A full reindex, the path
     * `cmsig:seal:reindex` takes, runs the provider's query without identifiers instead.
     */
    public function testFullReindexIndexesPublishedProductsAndVariants(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Parent', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($parentId);
        $draftId = $this->createProduct($familyId, 'Draft', ProductInterface::TYPE_PRODUCT);
        $this->publish($parentId);

        /** @var MessageBusInterface $messageBus */
        $messageBus = self::getContainer()->get('sulu_message_bus');
        $messageBus->dispatch(ReindexConfig::create()->withIndex(ProductIndex::NAME)->withDropIndex(true));

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        $this->assertSame($parentId, $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($parentId, 'en'))['resourceId']);
        $this->assertSame($variantId, $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($variantId, 'en'))['resourceId']);

        $this->expectException(DocumentNotFoundException::class);
        $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($draftId, 'en'));
    }

    public function testMediaIdComesFromTheDetailsImageAndIsNotInheritedByAVariant(): void
    {
        self::purgeDatabase();
        $media = self::createMedia(self::createCollection());
        self::getEntityManager()->flush();
        $mediaId = $media->getId();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct(
            $familyId,
            'Parent',
            ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
            ['image' => ['id' => $mediaId]],
        );
        $variantId = $this->createVariant($parentId);
        $this->publish($parentId);

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');

        // details/image is not multilingual, so it only exists on the unlocalized dimension content.
        $parent = $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($parentId, 'en'));
        $this->assertSame((string) $mediaId, $parent['mediaId']);

        // A variant keeps its own image; an empty one is not filled from the parent.
        $variant = $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($variantId, 'en'));
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
        $engine->getDocument(ProductIndex::NAME, ProductIndex::documentId($draftId, 'en'));
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
