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
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\RequestWorkflow\VariantRequestWorkflowResolver;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The variant overlay has no SEO or excerpt tab, so a variant can never fill what the pre-validators of the
 * product's request workflow require.
 */
#[CoversClass(VariantRequestWorkflowResolver::class)]
#[RunClassInSeparateProcess]
class ProductVariantRequestWorkflowTest extends SuluTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            ['environment' => 'test_request_workflow'],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testAVariantIsPublishedWithoutSeoAndExcerpt(): void
    {
        $parentId = $this->createPublishedParentWithSeoAndExcerpt();
        $variantId = $this->createVariant($parentId);

        $this->client->request('POST', '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=en&action=publish');

        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    public function testAProductWithoutSeoAndExcerptIsStillRejected(): void
    {
        $parentId = $this->createParent();

        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=publish');

        $this->assertHttpStatusCode(422, $this->client->getResponse());
        $this->assertStringContainsString('seo_required', (string) $this->client->getResponse()->getContent());
    }

    private function createPublishedParentWithSeoAndExcerpt(): string
    {
        $familyId = $this->createFamily();
        $parentId = $this->createParent($familyId);

        $this->client->request('PUT', '/admin/api/products/' . $parentId . '.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'title' => 'Parent',
            'productFamily' => $familyId,
            'type' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
            'seo' => ['title' => 'Parent SEO'],
            'excerpt' => ['title' => 'Parent excerpt'],
        ]) ?: null);
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        return $parentId;
    }

    private function createFamily(): string
    {
        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Family',
            'key' => \uniqid('family-'),
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        return $this->readId();
    }

    private function createParent(?string $familyId = null): string
    {
        $familyId ??= $this->createFamily();

        $this->client->request('POST', '/admin/api/products.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'title' => 'Parent',
            'productFamily' => $familyId,
            'type' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        return $this->readId();
    }

    private function createVariant(string $parentId): string
    {
        $this->client->request('POST', '/admin/api/products/' . $parentId . '/variants.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'code' => 'VARIANT-WORKFLOW',
            'title' => 'Variant',
            'url' => '/variant-workflow',
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        return $this->readId();
    }

    private function readId(): string
    {
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsString($data['id']);

        return $data['id'];
    }
}
