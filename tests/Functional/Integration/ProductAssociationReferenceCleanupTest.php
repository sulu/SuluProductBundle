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
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Reference\ProductAssociationReferenceCleanupSubscriber;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Proves that removing a product (Task 13) cleans up the stale reference record left behind on the
 * referrer product, since only the removed product's own reference records (as a referrer) are cleaned
 * up automatically - not the reference records where it is the target.
 */
#[CoversClass(ProductAssociationReferenceCleanupSubscriber::class)]
final class ProductAssociationReferenceCleanupTest extends SuluTestCase
{
    private KernelBrowser $client;

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
                'name' => 'Reference Cleanup Family',
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
            'url' => '/reference-cleanup-product-' . $counter,
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

    private function findReference(string $targetId, string $referrerId): ?object
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->clear();

        /** @var ReferenceRepositoryInterface $referenceRepository */
        $referenceRepository = self::getContainer()->get(ReferenceRepositoryInterface::class);

        return $referenceRepository->findOneBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => $targetId,
            'referenceResourceKey' => ProductDimensionContentInterface::RESOURCE_KEY,
            'referenceResourceId' => $referrerId,
        ]);
    }

    public function testCreatingProductWithAssociationWritesReferenceRecord(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $targetId = $this->createProduct($familyId, 'Target Product');
        $sourceId = $this->createProduct($familyId, 'Source Product', ['alternative' => [$targetId]]);

        $this->assertNotNull($this->findReference($targetId, $sourceId));
    }

    public function testRemovingReferencedProductCleansUpStaleReferenceOnReferrer(): void
    {
        self::purgeDatabase();
        $familyId = $this->createProductFamily();
        $targetId = $this->createProduct($familyId, 'Target Product');
        $sourceId = $this->createProduct($familyId, 'Source Product', ['alternative' => [$targetId]]);

        $this->assertNotNull($this->findReference($targetId, $sourceId));

        $this->client->request('DELETE', '/admin/api/products/' . $targetId . '.json?locale=en');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        $this->assertNull($this->findReference($targetId, $sourceId));
    }
}
