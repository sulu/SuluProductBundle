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
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\UserInterface\Controller\Admin\ProductComponentController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(ProductComponentController::class)]
class ProductComponentControllerTest extends SuluTestCase
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

    private function createProductFamily(string $name = 'Test Family'): string
    {
        $data = $this->request('POST', '/admin/api/product-families.json?locale=en', [
            'locale' => 'en',
            'name' => $name,
            'key' => \uniqid('family-'),
            'description' => null,
        ], 201);
        $this->assertIsString($data['id']);

        return $data['id'];
    }

    /**
     * @param array<string, array<int, string>> $associations
     */
    private function createProduct(
        string $familyId,
        string $title,
        string $type = ProductInterface::TYPE_PRODUCT,
        array $associations = [],
    ): string {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;

        $data = $this->request('POST', '/admin/api/products.json?locale=en', \array_filter([
            'locale' => 'en',
            'title' => $title,
            'code' => 'COMPONENT-' . $counter,
            'url' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS === $type ? null : '/test-component-' . $counter,
            'productFamily' => $familyId,
            'type' => $type,
            'associations' => [] === $associations ? null : $associations,
        ], static fn ($value) => null !== $value), 201);
        $this->assertIsString($data['id']);

        return $data['id'];
    }

    private function createVariant(string $parentId): string
    {
        $data = $this->request('POST', '/admin/api/products/' . $parentId . '/variants.json?locale=en', [
            'locale' => 'en',
            'code' => 'COMPONENT-VARIANT',
            'title' => 'Variant',
            'url' => '/test-component-variant',
        ], 201);
        $this->assertIsString($data['id']);

        return $data['id'];
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, ?array $payload, int $expectedStatus): array
    {
        $this->client->request($method, $uri, [], [], [], null === $payload ? null : (\json_encode($payload) ?: null));
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode($expectedStatus, $response);

        $content = (string) $response->getContent();
        if ('' === $content) {
            return [];
        }

        $data = \json_decode($content, true);
        $this->assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listComponents(string $parentId, string $locale = 'en'): array
    {
        $data = $this->request('GET', '/admin/api/products/' . $parentId . '/components.json?locale=' . $locale, null, 200);
        $this->assertIsArray($data['_embedded']);
        $this->assertIsArray($data['_embedded']['product_components']);

        /** @var array<int, array<string, mixed>> $items */
        $items = $data['_embedded']['product_components'];

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private function listComponentIds(string $parentId): array
    {
        return \array_map(static fn (array $item): string => \is_string($item['id']) ? $item['id'] : '', $this->listComponents($parentId));
    }

    private function addComponent(string $parentId, string $productId): void
    {
        $this->request('POST', '/admin/api/products/' . $parentId . '/components.json?locale=en', ['product' => $productId], 201);
    }

    /**
     * Workflow and timestamps of every dimension content of the product, to compare before and after a write.
     *
     * @return array<string, array<string, mixed>>
     */
    private function snapshotWorkflow(string $uuid): array
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->clear();
        /** @var ProductRepositoryInterface $repository */
        $repository = self::getContainer()->get(ProductRepositoryInterface::class);
        $product = $repository->getOneBy(['uuid' => $uuid]);

        $snapshot = [];
        foreach ($product->getDimensionContents() as $dimensionContent) {
            $snapshot[\sprintf('%s/%s/%s', $dimensionContent->getStage(), $dimensionContent->getLocale() ?? '-', $dimensionContent->getVersion())] = [
                'place' => $dimensionContent->getWorkflowPlace(),
                'published' => $dimensionContent->getWorkflowPublished()?->format('c'),
                'changed' => $dimensionContent->getChanged()->format('Y-m-d\TH:i:s.u'),
            ];
        }
        \ksort($snapshot);

        return $snapshot;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function createPublishedParentAndDraftComponents(): array
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        return [$parentId, $this->createProduct($familyId, 'Draft one'), $this->createProduct($familyId, 'Draft two')];
    }

    public function testWritesKeepTheParentWorkflowAndTheComponentsAsDraft(): void
    {
        [$parentId, $first, $second] = $this->createPublishedParentAndDraftComponents();
        $before = $this->snapshotWorkflow($parentId);
        // the timestamps have a resolution of one second
        \sleep(1);
        $this->assertSame('published', $before['draft/en/0']['place'] ?? null, \json_encode($before) ?: '');

        $this->addComponent($parentId, $first);
        $this->assertSame($before, $this->snapshotWorkflow($parentId), 'POST changed the parent');

        $this->request('PUT', '/admin/api/products/' . $parentId . '/components/' . $first . '.json?locale=en', ['product' => $second], 200);
        $this->assertSame($before, $this->snapshotWorkflow($parentId), 'PUT changed the parent');

        $this->client->request('DELETE', '/admin/api/products/' . $parentId . '/components/' . $second . '.json?locale=en');
        $this->assertHttpStatusCode(204, $this->client->getResponse());
        $this->assertSame($before, $this->snapshotWorkflow($parentId), 'DELETE changed the parent');
    }

    public function testListIsSortedByFamilyNameThenPosition(): void
    {
        self::purgeDatabase();

        $familyZ = $this->createProductFamily('Zeta family');
        $familyA = $this->createProductFamily('Alpha family');
        $parentId = $this->createProduct($familyA, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $firstZ = $this->createProduct($familyZ, 'First Z');
        $firstA = $this->createProduct($familyA, 'First A');
        $secondZ = $this->createProduct($familyZ, 'Second Z');
        $secondA = $this->createProduct($familyA, 'Second A');
        foreach ([$firstZ, $firstA, $secondZ, $secondA] as $component) {
            $this->addComponent($parentId, $component);
        }

        $items = $this->listComponents($parentId);

        $this->assertSame([$firstA, $secondA, $firstZ, $secondZ], \array_column($items, 'id'));
        $this->assertSame(['Alpha family', 'Alpha family', 'Zeta family', 'Zeta family'], \array_column($items, 'productFamily'));

        $data = $this->request('GET', '/admin/api/products/' . $parentId . '/components.json?locale=en&sortBy=name&sortOrder=asc', null, 200);
        $this->assertIsArray($data['_embedded']);
        $this->assertIsArray($data['_embedded']['product_components']);
        $this->assertSame([$firstA, $firstZ, $secondA, $secondZ], \array_column($data['_embedded']['product_components'], 'id'));
    }

    /**
     * @return array<int, string> the component uuids in the unlocalized live content of the parent
     */
    private function liveComponentUuids(string $parentId): array
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->clear();
        /** @var ProductRepositoryInterface $repository */
        $repository = self::getContainer()->get(ProductRepositoryInterface::class);

        $uuids = [];
        foreach ($repository->getOneBy(['uuid' => $parentId])->getDimensionContents() as $dimensionContent) {
            if (null === $dimensionContent->getLocale() && 'live' === $dimensionContent->getStage()) {
                foreach ($dimensionContent->getComponents() as $row) {
                    $uuids[] = $row->getComponent()->getUuid();
                }
            }
        }

        return $uuids;
    }

    private function rowUrl(string $parentId, string $componentId, string $action, string $locale = 'en'): string
    {
        return '/admin/api/products/' . $parentId . '/components/' . $componentId . '.json?locale=' . $locale . '&action=' . $action;
    }

    public function testAddedComponentIsDraftEvenIfItsProductIsPublished(): void
    {
        [$parentId, $first] = $this->createPublishedParentAndDraftComponents();
        $this->client->request('POST', '/admin/api/products/' . $first . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->addComponent($parentId, $first);

        $row = $this->indexById($this->listComponents($parentId))[$first];
        $this->assertFalse($row['publishedState']);
        $this->assertNull($row['published']);
        $this->assertSame([], $this->liveComponentUuids($parentId));
    }

    public function testPublishAndUnpublishARowLeaveEveryWorkflowUntouched(): void
    {
        [$parentId, $first, $second] = $this->createPublishedParentAndDraftComponents();
        $this->addComponent($parentId, $first);
        $this->addComponent($parentId, $second);
        $parentBefore = $this->snapshotWorkflow($parentId);
        $componentBefore = $this->snapshotWorkflow($first);
        \sleep(1);

        $data = $this->request('POST', $this->rowUrl($parentId, $first, 'publish'), null, 200);
        $this->assertSame(['id' => $first, 'product' => $first], $data);

        $rows = $this->indexById($this->listComponents($parentId));
        $this->assertTrue($rows[$first]['publishedState']);
        $this->assertNotNull($rows[$first]['published']);
        $this->assertNull($rows[$first]['ghostLocale']);
        $this->assertFalse($rows[$second]['publishedState']);
        $this->assertNull($rows[$second]['published']);
        $this->assertSame([$first], $this->liveComponentUuids($parentId));
        $this->assertSame($parentBefore, $this->snapshotWorkflow($parentId));
        $this->assertSame($componentBefore, $this->snapshotWorkflow($first));

        $rows = $this->indexById($this->listComponents($parentId, 'de'));
        $this->assertFalse($rows[$first]['publishedState']);
        $this->assertSame('en', $rows[$first]['ghostLocale']);

        $this->request('PUT', $this->rowUrl($parentId, $first, 'unpublish'), ['product' => $first], 200);
        $this->assertFalse($this->indexById($this->listComponents($parentId))[$first]['publishedState']);
        $this->assertSame([], $this->liveComponentUuids($parentId));
        $this->assertSame($parentBefore, $this->snapshotWorkflow($parentId));
    }

    public function testPutWithActionPublishesTheRow(): void
    {
        [$parentId, $first] = $this->createPublishedParentAndDraftComponents();
        $this->addComponent($parentId, $first);

        $this->request('PUT', $this->rowUrl($parentId, $first, 'publish'), ['product' => $first, 'component_rules' => ['x']], 200);

        $this->assertSame([$first], $this->liveComponentUuids($parentId));
    }

    public function testTheRowStateDoesNotFollowThePublishWorkflowOfTheParent(): void
    {
        [$parentId, $first, $second] = $this->createPublishedParentAndDraftComponents();
        $this->addComponent($parentId, $first);
        $this->addComponent($parentId, $second);
        $this->request('POST', $this->rowUrl($parentId, $first, 'publish'), null, 200);

        $assertRows = function() use ($parentId, $first, $second): void {
            $rows = $this->indexById($this->listComponents($parentId));
            $this->assertSame([$first], $this->liveComponentUuids($parentId));
            $this->assertSame([$first, $second], $this->listComponentIds($parentId));
            $this->assertArrayHasKey($first, $rows);
            $this->assertFalse($rows[$second]['publishedState']);
        };

        // the version of a publish is named by the second, so the transitions need a second between them
        \sleep(1);
        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $assertRows();
        $this->assertTrue($this->indexById($this->listComponents($parentId))[$first]['publishedState']);

        \sleep(1);
        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=unpublish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        // the unlocalized live content that holds the rows stays when a locale is unpublished
        $this->assertSame([$first], $this->liveComponentUuids($parentId));
        $this->assertFalse($this->indexById($this->listComponents($parentId))[$first]['publishedState']);

        \sleep(1);
        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        $assertRows();
        $this->assertTrue($this->indexById($this->listComponents($parentId))[$first]['publishedState']);
    }

    public function testRemovingTheDraftRowKeepsTheLiveRowUntilItIsUnpublished(): void
    {
        [$parentId, $first] = $this->createPublishedParentAndDraftComponents();
        $this->addComponent($parentId, $first);
        $this->request('POST', $this->rowUrl($parentId, $first, 'publish'), null, 200);

        $this->client->request('DELETE', '/admin/api/products/' . $parentId . '/components/' . $first . '.json?locale=en');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        $this->assertSame([$first], $this->liveComponentUuids($parentId));
        $this->assertSame([], $this->listComponentIds($parentId));
    }

    public function testPublishARowTwiceReturns409(): void
    {
        [$parentId, $first] = $this->createPublishedParentAndDraftComponents();
        $this->addComponent($parentId, $first);
        $this->request('POST', $this->rowUrl($parentId, $first, 'publish'), null, 200);

        $data = $this->request('POST', $this->rowUrl($parentId, $first, 'publish'), null, 409);

        $this->assertIsString($data['detail']);
    }

    public function testUnpublishADraftRowReturns409(): void
    {
        [$parentId, $first] = $this->createPublishedParentAndDraftComponents();
        $this->addComponent($parentId, $first);

        $this->request('POST', $this->rowUrl($parentId, $first, 'unpublish'), null, 409);
    }

    public function testPublishARowOfAParentThatWasNeverPublishedReturns409(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $component = $this->createProduct($familyId, 'Component');
        $this->addComponent($parentId, $component);

        $this->request('POST', $this->rowUrl($parentId, $component, 'publish'), null, 409);
        $this->assertFalse($this->listComponents($parentId)[0]['publishedState']);
    }

    public function testPublishARowInALocaleTheParentIsNotPublishedInReturns409(): void
    {
        [$parentId, $first] = $this->createPublishedParentAndDraftComponents();
        $this->addComponent($parentId, $first);

        $this->request('POST', $this->rowUrl($parentId, $first, 'publish', 'fr'), null, 409);
        $this->assertSame([], $this->liveComponentUuids($parentId));
    }

    public function testPostTriggerWithUnsupportedActionReturns400(): void
    {
        [$parentId, $first] = $this->createPublishedParentAndDraftComponents();
        $this->addComponent($parentId, $first);

        $data = $this->request('POST', $this->rowUrl($parentId, $first, 'remove'), null, 400);

        $this->assertIsString($data['detail']);
        $this->assertStringContainsString('Unsupported action "remove"', $data['detail']);
    }

    public function testPostTriggerOfAProductThatIsNoComponentIs404(): void
    {
        [$parentId, $other] = $this->createPublishedParentAndDraftComponents();

        $this->request('POST', $this->rowUrl($parentId, $other, 'publish'), null, 404);
        $this->request('PUT', $this->rowUrl($parentId, $other, 'publish'), null, 404);
        $this->assertSame([], $this->liveComponentUuids($parentId));
    }

    public function testPostAndPutIgnoreTheComponentRulesPlaceholder(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $first = $this->createProduct($familyId, 'First');
        $second = $this->createProduct($familyId, 'Second');

        $this->request('POST', '/admin/api/products/' . $parentId . '/components.json?locale=en', ['product' => $first, 'component_rules' => ['a' => 1]], 201);
        $this->request('PUT', '/admin/api/products/' . $parentId . '/components/' . $first . '.json?locale=en', ['product' => $second, 'component_rules' => null], 200);

        $this->assertSame([$second], $this->listComponentIds($parentId));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexById(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $this->assertIsString($row['id']);
            $indexed[$row['id']] = $row;
        }

        return $indexed;
    }

    public function testPostAppendsComponentsAndListShowsThemInOrder(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $first = $this->createProduct($familyId, 'Zeta');
        $second = $this->createProduct($familyId, 'Alpha');
        $unrelated = $this->createProduct($familyId, 'Unrelated');

        $data = $this->request('POST', '/admin/api/products/' . $parentId . '/components.json?locale=en', ['product' => $first], 201);
        $this->assertSame(['id' => $first, 'product' => $first], $data);
        $this->addComponent($parentId, $second);

        $items = $this->listComponents($parentId);
        $this->assertSame([$first, $second], \array_column($items, 'id'));
        $this->assertSame(['Zeta', 'Alpha'], \array_column($items, 'name'));
        foreach (['code', 'status', 'type'] as $column) {
            $this->assertArrayHasKey($column, $items[0]);
        }
        $this->assertNotContains($unrelated, \array_column($items, 'id'));
        $this->assertSame([], $this->listComponents($unrelated));
    }

    public function testListIsLimitedToTheParentComponents(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $otherParentId = $this->createProduct($familyId, 'Other bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $component = $this->createProduct($familyId, 'Component');
        $otherComponent = $this->createProduct($familyId, 'Other component');
        $this->addComponent($parentId, $component);
        $this->addComponent($otherParentId, $otherComponent);

        $this->assertSame([$component], $this->listComponentIds($parentId));
        $this->assertSame([$otherComponent], $this->listComponentIds($otherParentId));
    }

    public function testListCanBeSortedByName(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $zeta = $this->createProduct($familyId, 'Zeta');
        $alpha = $this->createProduct($familyId, 'Alpha');
        $this->addComponent($parentId, $zeta);
        $this->addComponent($parentId, $alpha);

        $data = $this->request('GET', '/admin/api/products/' . $parentId . '/components.json?locale=en&sortBy=name&sortOrder=asc', null, 200);
        $this->assertIsArray($data['_embedded']);
        $this->assertIsArray($data['_embedded']['product_components']);
        $this->assertSame([$alpha, $zeta], \array_column($data['_embedded']['product_components'], 'id'));
    }

    public function testPostKeepsTheAssociations(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $alternative = $this->createProduct($familyId, 'Alternative');
        $parentId = $this->createProduct(
            $familyId,
            'Bundle',
            ProductInterface::TYPE_CONFIGURABLE_PRODUCT,
            ['alternative' => [$alternative]],
        );
        $component = $this->createProduct($familyId, 'Component');

        $this->addComponent($parentId, $component);

        $product = $this->request('GET', '/admin/api/products/' . $parentId . '.json?locale=en', null, 200);
        $this->assertSame(
            ['alternative' => [$alternative], 'suitable' => []],
            $product['associations'],
        );
        $this->assertSame([$component], $product['components']);
    }

    public function testComponentsAreSharedByAllLocales(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $component = $this->createProduct($familyId, 'Component');

        $this->request('POST', '/admin/api/products/' . $parentId . '/components.json?locale=de', ['product' => $component], 201);

        $this->assertSame([$component], $this->listComponentIds($parentId));
        $this->assertSame([$component], \array_column($this->listComponents($parentId, 'de'), 'id'));
    }

    public function testGetReturnsTheComponent(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $component = $this->createProduct($familyId, 'Component');
        $this->addComponent($parentId, $component);

        $data = $this->request('GET', '/admin/api/products/' . $parentId . '/components/' . $component . '.json?locale=en', null, 200);

        $this->assertSame(['id' => $component, 'product' => $component], $data);
    }

    public function testGetOfAProductThatIsNoComponentIs404(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $other = $this->createProduct($familyId, 'Other');

        $this->request('GET', '/admin/api/products/' . $parentId . '/components/' . $other . '.json?locale=en', null, 404);
    }

    public function testListGetAndWriteOfAnUnknownParentAre404(): void
    {
        self::purgeDatabase();

        $unknown = '00000000-0000-4000-8000-000000000000';

        $this->request('GET', '/admin/api/products/' . $unknown . '/components/' . $unknown . '.json?locale=en', null, 404);
        $this->request('POST', '/admin/api/products/' . $unknown . '/components.json?locale=en', ['product' => $unknown], 404);
    }

    public function testPutReplacesTheComponentAtTheSamePosition(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $first = $this->createProduct($familyId, 'First');
        $second = $this->createProduct($familyId, 'Second');
        $replacement = $this->createProduct($familyId, 'Replacement');
        $this->addComponent($parentId, $first);
        $this->addComponent($parentId, $second);

        $data = $this->request(
            'PUT',
            '/admin/api/products/' . $parentId . '/components/' . $first . '.json?locale=en',
            ['product' => $replacement],
            200,
        );

        $this->assertSame(['id' => $replacement, 'product' => $replacement], $data);
        $this->assertSame([$replacement, $second], $this->listComponentIds($parentId));
    }

    public function testPutWithTheSameProductChangesNothing(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $component = $this->createProduct($familyId, 'Component');
        $this->addComponent($parentId, $component);

        $this->request('PUT', '/admin/api/products/' . $parentId . '/components/' . $component . '.json?locale=en', ['product' => $component], 200);

        $this->assertSame([$component], $this->listComponentIds($parentId));
    }

    public function testPutOfAProductThatIsNoComponentIs404(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $other = $this->createProduct($familyId, 'Other');

        $this->request('PUT', '/admin/api/products/' . $parentId . '/components/' . $other . '.json?locale=en', ['product' => $other], 404);
    }

    public function testPutRejectsAnotherExistingComponent(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $first = $this->createProduct($familyId, 'First');
        $second = $this->createProduct($familyId, 'Second');
        $this->addComponent($parentId, $first);
        $this->addComponent($parentId, $second);

        $this->request('PUT', '/admin/api/products/' . $parentId . '/components/' . $first . '.json?locale=en', ['product' => $second], 409);

        $this->assertSame([$first, $second], $this->listComponentIds($parentId));
    }

    public function testDeleteRemovesTheComponent(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $first = $this->createProduct($familyId, 'First');
        $second = $this->createProduct($familyId, 'Second');
        $this->addComponent($parentId, $first);
        $this->addComponent($parentId, $second);

        $this->request('DELETE', '/admin/api/products/' . $parentId . '/components/' . $first . '.json?locale=en', null, 204);

        $this->assertSame([$second], $this->listComponentIds($parentId));
        // the removed product itself stays
        $this->request('GET', '/admin/api/products/' . $first . '.json?locale=en', null, 200);
    }

    public function testDeleteOfAProductThatIsNoComponentIs404(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $other = $this->createProduct($familyId, 'Other');

        $this->request('DELETE', '/admin/api/products/' . $parentId . '/components/' . $other . '.json?locale=en', null, 404);
    }

    public function testPostRejectsAParentThatIsNotConfigurable(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Plain');
        $component = $this->createProduct($familyId, 'Component');

        $data = $this->request('POST', '/admin/api/products/' . $parentId . '/components.json?locale=en', ['product' => $component], 409);

        $this->assertSame(\sprintf('Product "%s" cannot have components.', $parentId), $data['detail']);
    }

    public function testPostRejectsAMissingProduct(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);

        $data = $this->request('POST', '/admin/api/products/' . $parentId . '/components.json?locale=en', [], 422);

        $this->assertSame('A product is required.', $data['detail']);
    }

    public function testPostRejectsTheProductItself(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);

        $data = $this->request('POST', '/admin/api/products/' . $parentId . '/components.json?locale=en', ['product' => $parentId], 422);

        $this->assertSame('A product cannot be a component of itself.', $data['detail']);
    }

    public function testPostRejectsAVariant(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $withVariants = $this->createProduct($familyId, 'With variants', ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $variantId = $this->createVariant($withVariants);

        $data = $this->request('POST', '/admin/api/products/' . $parentId . '/components.json?locale=en', ['product' => $variantId], 422);

        $this->assertSame(\sprintf('Product "%s" is a variant and cannot be a component.', $variantId), $data['detail']);
        $this->assertSame([], $this->listComponentIds($parentId));
    }

    public function testPostRejectsAnExistingComponent(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);
        $component = $this->createProduct($familyId, 'Component');
        $this->addComponent($parentId, $component);

        $data = $this->request('POST', '/admin/api/products/' . $parentId . '/components.json?locale=en', ['product' => $component], 409);

        $this->assertSame(\sprintf('Product "%s" is already a component of "%s".', $component, $parentId), $data['detail']);
    }

    public function testPostRejectsAnUnknownProduct(): void
    {
        self::purgeDatabase();

        $familyId = $this->createProductFamily();
        $parentId = $this->createProduct($familyId, 'Bundle', ProductInterface::TYPE_CONFIGURABLE_PRODUCT);

        $this->request('POST', '/admin/api/products/' . $parentId . '/components.json?locale=en', ['product' => '00000000-0000-4000-8000-000000000000'], 404);
    }
}
