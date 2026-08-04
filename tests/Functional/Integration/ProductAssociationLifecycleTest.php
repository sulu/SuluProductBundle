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
use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductAssociationsDataMapper;
use Sulu\Product\Infrastructure\Sulu\Content\Merger\ProductAssociationsMerger;
use Sulu\Product\Infrastructure\Sulu\Content\Normalizer\ProductAssociationsNormalizer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Proves that associations (Tasks 5-7: DataMapper/Merger/Normalizer trio) survive the
 * product content lifecycle - publish (draft -> live) and version restore - and that they
 * live on the unlocalized dimension content, so every locale of a product shares them.
 */
#[CoversClass(ProductAssociationsDataMapper::class)]
#[CoversClass(ProductAssociationsMerger::class)]
#[CoversClass(ProductAssociationsNormalizer::class)]
class ProductAssociationLifecycleTest extends SuluTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
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

    private function createProductFamily(): string
    {
        $this->client->request(
            'POST',
            '/admin/api/product-families.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'name' => 'Lifecycle Family',
                'description' => null,
            ]) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $familyId = $data['id'];
        $this->assertIsString($familyId);

        return $familyId;
    }

    /**
     * @param array<string, array<int, string>> $associations
     */
    private function createProduct(string $familyId, string $title, array $associations = []): string
    {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;

        $payload = [
            'locale' => 'en',
            'title' => $title,
            'url' => '/lifecycle-product-' . $counter,
            'productFamily' => $familyId,
        ];
        if ([] !== $associations) {
            $payload['associations'] = $associations;
        }

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode($payload) ?: null,
        );
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }

    /**
     * @param array<string, array<int, string>> $associations
     */
    private function putAssociations(string $id, string $locale, array $associations): void
    {
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=' . $locale,
            [],
            [],
            [],
            \json_encode([
                'locale' => $locale,
                'associations' => $associations,
            ]) ?: null,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    private function getProductWithDimensionContents(string $uuid): ProductInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->clear();

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = self::getContainer()->get(ProductRepositoryInterface::class);

        return $productRepository->getOneBy(['uuid' => $uuid]);
    }

    private function findCurrentDimensionContent(ProductInterface $product, ?string $locale, string $stage): ProductDimensionContentInterface
    {
        foreach ($product->getDimensionContents() as $dimensionContent) {
            if ($locale === $dimensionContent->getLocale()
                && $stage === $dimensionContent->getStage()
                && DimensionContentInterface::CURRENT_VERSION === $dimensionContent->getVersion()
            ) {
                return $dimensionContent;
            }
        }

        throw new \RuntimeException(\sprintf('Current dimension content for locale "%s" and stage "%s" not found.', $locale ?? 'null', $stage));
    }

    /**
     * @return array<int, string>
     */
    private function getAssociations(string $id, string $locale, string $type): array
    {
        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=' . $locale);
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsArray($data['associations']);
        $this->assertIsArray($data['associations'][$type]);

        /** @var array<int, string> $uuids */
        $uuids = $data['associations'][$type];

        return $uuids;
    }

    public function testPublishCopiesAssociationsWithDistinctRows(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $targetId = $this->createProduct($familyId, 'Target Product');
        $sourceId = $this->createProduct($familyId, 'Source Product', ['alternative' => [$targetId]]);

        $this->client->request('POST', '/admin/api/products/' . $sourceId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $product = $this->getProductWithDimensionContents($sourceId);
        $draft = $this->findCurrentDimensionContent($product, null, DimensionContentInterface::STAGE_DRAFT);
        $live = $this->findCurrentDimensionContent($product, null, DimensionContentInterface::STAGE_LIVE);

        $draftAssociations = $draft->getAssociationsByType('alternative');
        $liveAssociations = $live->getAssociationsByType('alternative');

        $this->assertCount(1, $draftAssociations);
        $this->assertCount(1, $liveAssociations);

        $draftAssociation = $draftAssociations[0];
        $liveAssociation = $liveAssociations[0];

        $this->assertSame($targetId, $draftAssociation->getTarget()->getUuid());
        $this->assertSame($targetId, $liveAssociation->getTarget()->getUuid());

        // The live row must be a genuinely new, distinct row - not the same instance copied over.
        $this->assertNotSame($draftAssociation->getId(), $liveAssociation->getId());
        $this->assertSame($draft, $draftAssociation->getProductDimensionContent());
        $this->assertSame($live, $liveAssociation->getProductDimensionContent());
    }

    public function testRestoreVersionCarriesAssociations(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $targetId = $this->createProduct($familyId, 'Target Product');
        $sourceId = $this->createProduct($familyId, 'Source Product', ['alternative' => [$targetId]]);

        // Publish to create a version snapshot (PublishTransitionSubscriber stores version = time()).
        $this->client->request('POST', '/admin/api/products/' . $sourceId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->client->request('GET', '/admin/api/products/' . $sourceId . '/versions.json?locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $versionsData = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($versionsData);
        /** @var array{_embedded: array{products_versions: list<array{version: int}>}} $versionsData */
        $versions = $versionsData['_embedded']['products_versions'];
        $this->assertNotEmpty($versions);
        $version = $versions[0]['version'];

        // Clear the draft association so a subsequent restore is a meaningful assertion.
        $this->putAssociations($sourceId, 'en', ['alternative' => []]);

        $product = $this->getProductWithDimensionContents($sourceId);
        $draftBeforeRestore = $this->findCurrentDimensionContent($product, null, DimensionContentInterface::STAGE_DRAFT);
        $this->assertCount(0, $draftBeforeRestore->getAssociationsByType('alternative'));

        $this->client->request(
            'POST',
            '/admin/api/products/' . $sourceId . '.json?locale=en&action=restore&version=' . $version,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $product = $this->getProductWithDimensionContents($sourceId);
        $draftAfterRestore = $this->findCurrentDimensionContent($product, null, DimensionContentInterface::STAGE_DRAFT);
        $associations = $draftAfterRestore->getAssociationsByType('alternative');

        $this->assertCount(1, $associations);
        $this->assertSame($targetId, $associations[0]->getTarget()->getUuid());
    }

    public function testEveryLocaleSharesTheSameAssociations(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $targetId = $this->createProduct($familyId, 'Target Product');
        $sourceId = $this->createProduct($familyId, 'Source Product', ['alternative' => [$targetId]]);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $sourceId . '.json?locale=en&action=copy_locale&src=en&dest=de',
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->assertSame([$targetId], $this->getAssociations($sourceId, 'en', 'alternative'));
        $this->assertSame([$targetId], $this->getAssociations($sourceId, 'de', 'alternative'));

        $product = $this->getProductWithDimensionContents($sourceId);
        $unlocalizedDraft = $this->findCurrentDimensionContent($product, null, DimensionContentInterface::STAGE_DRAFT);
        $enDraft = $this->findCurrentDimensionContent($product, 'en', DimensionContentInterface::STAGE_DRAFT);
        $deDraft = $this->findCurrentDimensionContent($product, 'de', DimensionContentInterface::STAGE_DRAFT);

        $this->assertCount(1, $unlocalizedDraft->getAssociationsByType('alternative'));
        $this->assertSame($targetId, $unlocalizedDraft->getAssociationsByType('alternative')[0]->getTarget()->getUuid());
        $this->assertSame([], $enDraft->getAssociations());
        $this->assertSame([], $deDraft->getAssociations());

        $this->putAssociations($sourceId, 'de', ['alternative' => []]);

        $this->assertSame([], $this->getAssociations($sourceId, 'en', 'alternative'));
        $this->assertSame([], $this->getAssociations($sourceId, 'de', 'alternative'));
    }
}
