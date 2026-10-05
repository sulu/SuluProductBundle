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

namespace Sulu\Product\Tests\Functional\Infrastructure\Sulu\Trash;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ActivityBundle\Domain\Model\ActivityInterface;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfigurationProviderInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @property KernelBrowser $client
 */
trait TrashTestTrait
{
    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $uri, ?array $body = null, int $expectedStatus = 200): array
    {
        $this->client->request($method, $uri, [], [], [], null !== $body ? (\json_encode($body) ?: null) : null);
        $this->assertHttpStatusCode($expectedStatus, $this->client->getResponse());

        /** @var array<string, mixed> $data */
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];

        return $data;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postForId(string $uri, array $body): string
    {
        $id = $this->requestJson('POST', $uri, $body, 201)['id'] ?? null;
        $this->assertIsString($id);

        return $id;
    }

    private function findTrashItemId(string $resourceKey, string $resourceId): int
    {
        $trashItem = $this->getClearedEntityManager()->getRepository(TrashItemInterface::class)->findOneBy([
            'resourceKey' => $resourceKey,
            'resourceId' => $resourceId,
        ]);
        $this->assertNotNull($trashItem);

        return $trashItem->getId();
    }

    /**
     * @return array<string, mixed>
     */
    private function restoreTrashItem(int $trashItemId, int $expectedStatus = 200): array
    {
        return $this->requestJson('POST', '/admin/api/trash-items/' . $trashItemId . '.json?action=restore', [], $expectedStatus);
    }

    /**
     * The admin navigates to the restored item with these values, so each has to be the uuid.
     *
     * @param array<string, mixed> $restored
     */
    private function assertRestoreNavigatesTo(string $handlerServiceId, array $restored, string $expectedId): void
    {
        /** @var RestoreConfigurationProviderInterface $handler */
        $handler = self::getContainer()->get($handlerServiceId);
        $resultToView = $handler->getConfiguration()->getResultToView() ?? [];

        $this->assertSame(['id'], \array_values($resultToView));
        foreach (\array_keys($resultToView) as $resultPath) {
            $this->assertSame($expectedId, $restored[$resultPath] ?? null);
        }
    }

    /**
     * @return list<string>
     */
    private function findActivityTypes(string $resourceKey, string $resourceId): array
    {
        $activities = $this->getClearedEntityManager()->getRepository(ActivityInterface::class)->findBy(
            ['resourceKey' => $resourceKey, 'resourceId' => $resourceId],
            ['timestamp' => 'ASC'],
        );

        return \array_map(static fn (ActivityInterface $activity) => $activity->getType(), $activities);
    }

    private function getClearedEntityManager(): EntityManagerInterface
    {
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        return $entityManager;
    }
}
