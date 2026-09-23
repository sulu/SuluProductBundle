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
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\ReferenceBundle\Domain\Model\ReferenceInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Component\Localization\Manager\LocalizationManagerInterface;
use Sulu\Content\Tests\Functional\Traits\CreateMediaTrait;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Infrastructure\Sulu\Reference\ProductFamilyReferenceDoctrineEventListener;
use Sulu\Product\Infrastructure\Sulu\Reference\ProductFamilyReferenceRefresher;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ProductFamilyReferenceRefresher::class)]
#[CoversClass(ProductFamilyReferenceDoctrineEventListener::class)]
final class ProductFamilyReferenceTest extends SuluTestCase
{
    use CreateMediaTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testTheFamilyReferencesItsImageInEveryLocale(): void
    {
        $imageId = $this->createMediaId();

        $familyId = $this->saveFamily('POST', null, ['name' => 'Connectors', 'image' => ['id' => $imageId]]);

        $references = $this->findFamilyReferences($familyId);
        self::assertSame($this->getLocales(), $this->column($references, 'referenceLocale'));
        foreach ($references as $reference) {
            self::assertSame(MediaInterface::RESOURCE_KEY, $reference->getResourceKey());
            self::assertSame((string) $imageId, $reference->getResourceId());
            self::assertSame('image', $reference->getReferenceProperty());
            self::assertSame('Connectors', $reference->getReferenceTitle());
        }
    }

    public function testChangingTheImageMovesTheReference(): void
    {
        $oldImageId = $this->createMediaId();
        $newImageId = $this->createMediaId();
        $familyId = $this->saveFamily('POST', null, ['name' => 'Connectors', 'image' => ['id' => $oldImageId]]);

        $this->saveFamily('PUT', $familyId, ['name' => 'Connectors', 'image' => ['id' => $newImageId]]);

        self::assertSame([(string) $newImageId], \array_values(\array_unique(
            $this->column($this->findFamilyReferences($familyId), 'resourceId'),
        )));
    }

    public function testClearingTheImageRemovesTheReference(): void
    {
        $familyId = $this->saveFamily('POST', null, ['name' => 'Connectors', 'image' => ['id' => $this->createMediaId()]]);
        self::assertNotSame([], $this->findFamilyReferences($familyId));

        $this->saveFamily('PUT', $familyId, ['name' => 'Connectors', 'image' => null]);

        self::assertSame([], $this->findFamilyReferences($familyId));
    }

    public function testRenamingTheFamilyUpdatesTheReferenceTitle(): void
    {
        $imageId = $this->createMediaId();
        $familyId = $this->saveFamily('POST', null, ['name' => 'Connectors', 'image' => ['id' => $imageId]]);

        $this->saveFamily('PUT', $familyId, ['name' => 'Cables', 'image' => ['id' => $imageId]]);

        self::assertSame(['Cables'], \array_values(\array_unique(
            $this->column($this->findFamilyReferences($familyId), 'referenceTitle'),
        )));
    }

    public function testRemovingTheFamilyRemovesItsReferences(): void
    {
        $familyId = $this->saveFamily('POST', null, ['name' => 'Connectors', 'image' => ['id' => $this->createMediaId()]]);
        self::assertNotSame([], $this->findFamilyReferences($familyId));

        $this->client->request('DELETE', '/admin/api/product-families/' . $familyId . '.json?locale=en');
        $this->assertHttpStatusCode(204, $this->client->getResponse());

        self::assertSame([], $this->findFamilyReferences($familyId));
    }

    public function testTheRefresherIsIndexedByTheFamilyResourceKey(): void
    {
        self::assertSame(ProductFamilyInterface::RESOURCE_KEY, ProductFamilyReferenceRefresher::getResourceKey());
    }

    public function testTheRefreshCommandRebuildsTheReferences(): void
    {
        $familyId = $this->saveFamily('POST', null, ['name' => 'Connectors', 'image' => ['id' => $this->createMediaId()]]);
        $expected = $this->column($this->findFamilyReferences($familyId), 'resourceId');
        self::getEntityManager()->createQuery('DELETE FROM ' . ReferenceInterface::class . ' reference')->execute();

        $command = (new Application(self::bootKernel()))->find('sulu:reference:refresh');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['resource-key' => ProductFamilyInterface::RESOURCE_KEY]);
        $commandTester->assertCommandIsSuccessful();

        self::assertSame($expected, $this->column($this->findFamilyReferences($familyId), 'resourceId'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function saveFamily(string $method, ?string $familyId, array $data): string
    {
        $url = '/admin/api/product-families' . (null !== $familyId ? '/' . $familyId : '') . '.json?locale=en';
        $this->client->request($method, $url, [], [], [], \json_encode(['locale' => 'en', ...$data]) ?: null);
        $this->assertHttpStatusCode('POST' === $method ? 201 : 200, $this->client->getResponse());

        $response = \json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($response);
        self::assertIsString($response['id']);

        return $response['id'];
    }

    private function createMediaId(): int
    {
        $media = self::createMedia(self::createCollection());
        self::getEntityManager()->flush();

        return $media->getId();
    }

    /**
     * @return list<ReferenceInterface>
     */
    private function findFamilyReferences(string $familyId): array
    {
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        /** @var list<ReferenceInterface> $references */
        $references = $entityManager->getRepository(ReferenceInterface::class)->findBy(
            ['referenceResourceKey' => ProductFamilyInterface::RESOURCE_KEY, 'referenceResourceId' => $familyId],
            ['referenceLocale' => 'ASC'],
        );

        return $references;
    }

    /**
     * @param list<ReferenceInterface> $references
     * @param 'referenceLocale'|'referenceTitle'|'resourceId' $field
     *
     * @return list<string>
     */
    private function column(array $references, string $field): array
    {
        return \array_map(static fn (ReferenceInterface $reference): string => match ($field) {
            'referenceLocale' => (string) $reference->getReferenceLocale(),
            'referenceTitle' => $reference->getReferenceTitle(),
            'resourceId' => $reference->getResourceId(),
        }, $references);
    }

    /**
     * @return list<string>
     */
    private function getLocales(): array
    {
        /** @var LocalizationManagerInterface $localizationManager */
        $localizationManager = self::getContainer()->get('sulu.core.localization_manager');
        $locales = \array_values($localizationManager->getLocales());
        \sort($locales);

        return $locales;
    }
}
