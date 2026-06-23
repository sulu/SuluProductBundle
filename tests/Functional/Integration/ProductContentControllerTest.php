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
use PHPUnit\Framework\Attributes\Depends;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\UserInterface\Controller\Admin\ProductContentController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(ProductContentController::class)]
class ProductContentControllerTest extends SuluTestCase
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
        $container = self::getContainer();

        /** @var ProductFamilyRepositoryInterface $familyRepository */
        $familyRepository = $container->get(ProductFamilyRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $family = $familyRepository->create();
        $familyRepository->save($family);
        $entityManager->flush();

        $uuid = $family->getUuid();
        self::assertNotNull($uuid);

        return $uuid;
    }

    public function testPostProductForFollowingTests(): string
    {
        self::purgeDatabase();

        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'template' => 'product',
                'productFamily' => $this->createProductFamily(),
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(201, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }

    #[Depends('testPostProductForFollowingTests')]
    public function testGetContent(string $id): string
    {
        $this->client->request('GET', '/admin/api/product-contents/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        return $id;
    }

    public function testGetContentReturnsNotFoundForUnknownId(): void
    {
        $this->client->request('GET', '/admin/api/product-contents/non-existent-uuid.json?locale=en');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(404, $response);
    }

    #[Depends('testGetContent')]
    public function testPutContent(string $id): string
    {
        $this->client->request(
            'PUT',
            '/admin/api/product-contents/' . $id . '.json?locale=en',
            [],
            [],
            [],
            \json_encode([
                'locale' => 'en',
                'template' => 'product',
                'title' => 'My Product Title',
                'url' => '/my-product-title',
            ]) ?: null,
        );

        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        return $id;
    }

    #[Depends('testPutContent')]
    public function testGetVersions(string $id): void
    {
        $this->client->request('GET', '/admin/api/product-contents/' . $id . '/versions.json?locale=en');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
    }

    #[Depends('testPutContent')]
    public function testPostDraftAction(string $id): void
    {
        $this->client->request(
            'POST',
            '/admin/api/product-contents/' . $id . '.json?locale=en&action=draft',
        );
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);
    }

    #[Depends('testPutContent')]
    public function testPostCopyLocaleAction(string $id): void
    {
        $this->client->request(
            'POST',
            '/admin/api/product-contents/' . $id . '.json?locale=de&action=copy_locale&src=en&dest=de',
        );
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);
    }

    #[Depends('testPutContent')]
    public function testPostRestoreAction(string $id): void
    {
        // First publish the product to create a version snapshot (version = Unix timestamp)
        $this->client->request(
            'POST',
            '/admin/api/product-contents/' . $id . '.json?locale=en&action=publish',
        );
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        // Retrieve the version list to get the actual version number
        $this->client->request('GET', '/admin/api/product-contents/' . $id . '/versions.json?locale=en');
        $versionsResponse = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $versionsResponse);
        $versionsData = \json_decode((string) $versionsResponse->getContent(), true);
        $this->assertIsArray($versionsData);
        /** @var array{_embedded: array{products_versions: list<array{version: string}>}} $versionsData */
        $versions = $versionsData['_embedded']['products_versions'];
        $this->assertNotEmpty($versions, 'Expected at least one version after publishing');
        $version = $versions[0]['version'];

        // Now restore to that version
        $this->client->request(
            'POST',
            '/admin/api/product-contents/' . $id . '.json?locale=en&action=restore&version=' . $version,
        );
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);
    }

    #[Depends('testPutContent')]
    public function testPostRestoreActionRequiresVersion(string $id): void
    {
        $this->client->request(
            'POST',
            '/admin/api/product-contents/' . $id . '.json?locale=en&action=restore',
        );
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(500, $response);
    }

    #[Depends('testPutContent')]
    public function testPostPublishAction(string $id): void
    {
        $this->client->request(
            'POST',
            '/admin/api/product-contents/' . $id . '.json?locale=en&action=publish',
        );
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);
    }

    #[Depends('testPutContent')]
    public function testHandleActionNoAction(string $id): void
    {
        $this->client->request(
            'POST',
            '/admin/api/product-contents/' . $id . '.json?locale=en',
        );
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);
    }

    public function testGetContentReturnsTemplateOnlyWhenNoContent(): void
    {
        self::purgeDatabase();

        // Create a product WITHOUT a template so no dimension content is persisted
        $this->client->request(
            'POST',
            '/admin/api/products.json?locale=en',
            [],
            [],
            [],
            \json_encode(['locale' => 'en', 'productFamily' => $this->createProductFamily()]) ?: null,
        );
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(201, $response);
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        // GET triggers ContentNotFoundException because there are no dimension contents
        $this->client->request('GET', '/admin/api/product-contents/' . $id . '.json?locale=en');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);

        $responseData = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('template', $responseData);
        $this->assertSame('product', $responseData['template']);
    }
}
