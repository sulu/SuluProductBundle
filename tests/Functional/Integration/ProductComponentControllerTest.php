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

use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\ProductInterface;
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

    private function createProductFamily(): string
    {
        $data = $this->request('POST', '/admin/api/product-families.json?locale=en', [
            'locale' => 'en',
            'name' => 'Test Family',
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
