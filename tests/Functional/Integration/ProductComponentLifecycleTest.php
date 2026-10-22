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
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductComponentsDataMapper;
use Sulu\Product\Infrastructure\Sulu\Content\Merger\ProductComponentsMerger;
use Sulu\Product\Infrastructure\Sulu\Content\Normalizer\ProductComponentsNormalizer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Proves that components survive the product content lifecycle (publish, copy locale, version
 * restore), live on the unlocalized dimension content and keep their rows when a save repeats them.
 */
#[CoversClass(ProductComponentsDataMapper::class)]
#[CoversClass(ProductComponentsMerger::class)]
#[CoversClass(ProductComponentsNormalizer::class)]
class ProductComponentLifecycleTest extends SuluTestCase
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
                'key' => \uniqid('family-'),
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
     * @param array<int, string> $components
     */
    private function createProduct(string $familyId, string $title, array $components = []): string
    {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;

        $payload = [
            'locale' => 'en',
            'title' => $title,
            'url' => '/lifecycle-component-' . $counter,
            'productFamily' => $familyId,
        ];
        if ([] !== $components) {
            $payload['components'] = $components;
        }

        $this->client->request('POST', '/admin/api/products.json?locale=en', [], [], [], \json_encode($payload) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function putProduct(string $id, string $locale, array $data): void
    {
        $this->client->request(
            'PUT',
            '/admin/api/products/' . $id . '.json?locale=' . $locale,
            [],
            [],
            [],
            \json_encode(['locale' => $locale, ...$data]) ?: null,
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
    private function getComponents(string $id, string $locale): array
    {
        $this->client->request('GET', '/admin/api/products/' . $id . '.json?locale=' . $locale);
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsArray($data['components']);

        /** @var array<int, string> $uuids */
        $uuids = $data['components'];

        return $uuids;
    }

    public function testCreateStoresTheComponentsInOrder(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $first = $this->createProduct($familyId, 'First');
        $second = $this->createProduct($familyId, 'Second');
        $sourceId = $this->createProduct($familyId, 'Source Product', [$second, $first]);

        $this->assertSame([$second, $first], $this->getComponents($sourceId, 'en'));
    }

    public function testSavingWithoutComponentsKeepsThem(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $componentId = $this->createProduct($familyId, 'Component');
        $sourceId = $this->createProduct($familyId, 'Source Product', [$componentId]);

        $this->putProduct($sourceId, 'en', ['title' => 'Renamed']);

        $this->assertSame([$componentId], $this->getComponents($sourceId, 'en'));
    }

    public function testSavingTheSameComponentsKeepsTheRowIds(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $first = $this->createProduct($familyId, 'First');
        $second = $this->createProduct($familyId, 'Second');
        $sourceId = $this->createProduct($familyId, 'Source Product', [$first, $second]);

        $before = $this->findCurrentDimensionContent($this->getProductWithDimensionContents($sourceId), null, DimensionContentInterface::STAGE_DRAFT);
        $idsBefore = [];
        foreach ($before->getComponents() as $component) {
            $idsBefore[$component->getComponent()->getUuid()] = $component->getId();
        }

        $this->putProduct($sourceId, 'en', ['components' => [$second, $first]]);

        $after = $this->findCurrentDimensionContent($this->getProductWithDimensionContents($sourceId), null, DimensionContentInterface::STAGE_DRAFT);
        $idsAfter = [];
        foreach ($after->getComponents() as $component) {
            $idsAfter[$component->getComponent()->getUuid()] = $component->getId();
        }

        $this->assertSame([$second, $first], \array_keys($idsAfter));
        $this->assertEquals($idsBefore, $idsAfter);
    }

    public function testPublishCopiesComponentsWithDistinctRows(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $componentId = $this->createProduct($familyId, 'Component');
        $sourceId = $this->createProduct($familyId, 'Source Product', [$componentId]);

        $this->client->request('POST', '/admin/api/products/' . $sourceId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $product = $this->getProductWithDimensionContents($sourceId);
        $draft = $this->findCurrentDimensionContent($product, null, DimensionContentInterface::STAGE_DRAFT);
        $live = $this->findCurrentDimensionContent($product, null, DimensionContentInterface::STAGE_LIVE);

        $this->assertCount(1, $draft->getComponents());
        $this->assertCount(1, $live->getComponents());

        $draftComponent = $draft->getComponents()[0];
        $liveComponent = $live->getComponents()[0];

        $this->assertSame($componentId, $draftComponent->getComponent()->getUuid());
        $this->assertSame($componentId, $liveComponent->getComponent()->getUuid());
        $this->assertNotSame($draftComponent->getId(), $liveComponent->getId());
        $this->assertSame($draft, $draftComponent->getProductDimensionContent());
        $this->assertSame($live, $liveComponent->getProductDimensionContent());
    }

    public function testRestoreVersionCarriesComponents(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $componentId = $this->createProduct($familyId, 'Component');
        $sourceId = $this->createProduct($familyId, 'Source Product', [$componentId]);

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

        $this->putProduct($sourceId, 'en', ['components' => []]);
        $this->assertSame([], $this->getComponents($sourceId, 'en'));

        $this->client->request(
            'POST',
            '/admin/api/products/' . $sourceId . '.json?locale=en&action=restore&version=' . $version,
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->assertSame([$componentId], $this->getComponents($sourceId, 'en'));
    }

    public function testEveryLocaleSharesTheSameComponents(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $componentId = $this->createProduct($familyId, 'Component');
        $sourceId = $this->createProduct($familyId, 'Source Product', [$componentId]);

        $this->client->request(
            'POST',
            '/admin/api/products/' . $sourceId . '.json?locale=en&action=copy_locale&src=en&dest=de',
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->assertSame([$componentId], $this->getComponents($sourceId, 'en'));
        $this->assertSame([$componentId], $this->getComponents($sourceId, 'de'));

        $product = $this->getProductWithDimensionContents($sourceId);
        $this->assertSame([], $this->findCurrentDimensionContent($product, 'en', DimensionContentInterface::STAGE_DRAFT)->getComponents());
        $this->assertSame([], $this->findCurrentDimensionContent($product, 'de', DimensionContentInterface::STAGE_DRAFT)->getComponents());

        $this->putProduct($sourceId, 'de', ['components' => []]);

        $this->assertSame([], $this->getComponents($sourceId, 'en'));
        $this->assertSame([], $this->getComponents($sourceId, 'de'));
    }

    public function testRemovingAComponentProductRemovesItsRows(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $kept = $this->createProduct($familyId, 'Kept');
        $removed = $this->createProduct($familyId, 'Removed');
        $sourceId = $this->createProduct($familyId, 'Source Product', [$removed, $kept]);

        $this->client->request('DELETE', '/admin/api/products/' . $removed . '.json?locale=en');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        $this->assertSame([$kept], $this->getComponents($sourceId, 'en'));
    }
}
