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

namespace Sulu\Product\Infrastructure\Sulu\Reference;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Sulu\Bundle\ReferenceBundle\Application\Message\RefreshReferenceMessage;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslationInterface;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Refreshes the references of every family written in a flush, and removes those of a removed
 * family. A translation counts as a write of its family, as it carries the reference title.
 *
 * @internal
 */
class ProductFamilyReferenceDoctrineEventListener implements ResetInterface
{
    use HandleTrait;

    /**
     * @var array<string, true>
     */
    private array $writtenUuids = [];

    /**
     * @var array<string, true>
     */
    private array $removedUuids = [];

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly ReferenceRepositoryInterface $referenceRepository,
    ) {
        $this->messageBus = $messageBus;
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $unitOfWork = $eventArgs->getObjectManager()->getUnitOfWork();

        $entities = [
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
            ...$unitOfWork->getScheduledEntityDeletions(),
        ];

        foreach ($entities as $entity) {
            $family = match (true) {
                $entity instanceof ProductFamilyInterface => $entity,
                $entity instanceof ProductFamilyTranslationInterface => $entity->getFamily(),
                default => null,
            };

            if (null !== $family?->getUuid()) {
                $this->writtenUuids[$family->getUuid()] = true;
            }
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof ProductFamilyInterface && null !== $entity->getUuid()) {
                $this->removedUuids[$entity->getUuid()] = true;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
        $writtenUuids = \array_diff_key($this->writtenUuids, $this->removedUuids);
        $removedUuids = $this->removedUuids;

        // Reset first: the refresh flushes again.
        $this->reset();

        foreach (\array_keys($writtenUuids) as $uuid) {
            $this->handle(new RefreshReferenceMessage(ProductFamilyInterface::RESOURCE_KEY, (string) $uuid, '', ''));
        }

        foreach (\array_keys($removedUuids) as $uuid) {
            $this->referenceRepository->removeBy([
                'referenceResourceKey' => ProductFamilyInterface::RESOURCE_KEY,
                'referenceResourceId' => (string) $uuid,
            ]);
        }
    }

    public function onClear(): void
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->writtenUuids = [];
        $this->removedUuids = [];
    }
}
