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
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Under `per_product` a variant owns no route, so its document carries the code and the parent's
 * url for the template to link it. Runs in separate processes because a second kernel environment
 * redeclares Sulu's generated cache classes.
 */
#[RunTestsInSeparateProcesses]
class WebsiteProductReindexPerProductTest extends SuluTestCase
{
    /**
     * @return array<string, mixed>
     */
    protected static function getKernelConfiguration(): array
    {
        return ['environment' => 'test_variant_per_product'];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testAVariantDocumentCarriesItsCodeAndTheParentUrl(): void
    {
        self::purgeDatabase();
        self::ensureKernelShutdown();
        $client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );

        $client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'name' => 'Shirts',
            'key' => 'shirts',
        ]) ?: null);
        $this->assertHttpStatusCode(201, $client->getResponse());
        $familyId = $this->getId($client);

        $client->request('POST', '/admin/api/products.json?locale=en', [], [], [], \json_encode([
            'title' => 'Shirt',
            'url' => '/shirt',
            'productFamily' => $familyId,
            'type' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $client->getResponse());
        $parentId = $this->getId($client);

        $client->request('POST', '/admin/api/products/' . $parentId . '/variants.json?locale=en', [], [], [], \json_encode([
            'title' => 'Shirt Red',
            'code' => 'RED 1',
        ]) ?: null);
        $this->assertHttpStatusCode(201, $client->getResponse());
        $variantId = $this->getId($client);

        $client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $client->getResponse());
        $client->request('POST', '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $client->getResponse());

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');
        $variant = $engine->getDocument('website', 'products__' . $variantId . '__en');

        $this->assertSame('', $variant['url'], 'A variant owns no route.');
        $this->assertSame(['code' => 'RED 1', 'parentUrl' => '/shirt'], $variant['metadata']);
    }

    private function getId(KernelBrowser $client): string
    {
        $data = \json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertIsString($data['id']);

        return $data['id'];
    }
}
