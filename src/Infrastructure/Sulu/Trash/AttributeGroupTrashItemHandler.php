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

namespace Sulu\Product\Infrastructure\Sulu\Trash;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfiguration;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfigurationProviderInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\RestoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\StoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Product\Domain\Event\AttributeGroupRestoredEvent;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeGroupAdmin;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type AttributeGroupRestoreData array{
 *     externalIdentifier: string|null,
 *     defaultLocale: string|null,
 *     created: string,
 *     creatorId: int|null,
 *     translations: list<array{locale: string, name: string, description: string|null}>,
 * }
 *
 * @internal
 */
final class AttributeGroupTrashItemHandler implements
    StoreTrashItemHandlerInterface,
    RestoreTrashItemHandlerInterface,
    RestoreConfigurationProviderInterface
{
    public function __construct(
        private TrashItemRepositoryInterface $trashItemRepository,
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
        private EntityManagerInterface $entityManager,
        private DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public static function getResourceKey(): string
    {
        return AttributeGroupInterface::RESOURCE_KEY;
    }

    public function store(object $resource, array $options = []): TrashItemInterface
    {
        Assert::isInstanceOf($resource, AttributeGroupInterface::class);
        $group = $resource;

        $titles = [];
        $translations = [];
        foreach ($group->getTranslations() as $translation) {
            $titles[$translation->getLocale()] = $translation->getName();
            $translations[] = [
                'locale' => $translation->getLocale(),
                'name' => $translation->getName(),
                'description' => $translation->getDescription(),
            ];
        }

        $data = [
            'externalIdentifier' => $group->getExternalIdentifier(),
            'defaultLocale' => $group->getDefaultLocale(),
            'created' => $group->getCreated()->format('c'),
            'creatorId' => $group->getCreator()?->getId(),
            'translations' => $translations,
        ];

        return $this->trashItemRepository->create(
            AttributeGroupInterface::RESOURCE_KEY,
            $group->getUuid(),
            $titles,
            $data,
            null,
            $options,
            AttributeGroupAdmin::SECURITY_CONTEXT,
            null,
            null,
        );
    }

    public function restore(TrashItemInterface $trashItem, array $restoreFormData = []): object
    {
        /** @var AttributeGroupRestoreData $data */
        $data = $trashItem->getRestoreData();

        $group = $this->attributeGroupRepository->createNew($trashItem->getResourceId());
        $group->setExternalIdentifier($data['externalIdentifier']);
        if (null !== $data['defaultLocale']) {
            $group->setDefaultLocale($data['defaultLocale']);
        }
        $group->setCreated(new \DateTimeImmutable($data['created']));
        $group->setCreator(null !== $data['creatorId'] ? $this->entityManager->find(UserInterface::class, $data['creatorId']) : null);

        foreach ($data['translations'] as $translationData) {
            $translation = new AttributeGroupTranslation($group, $translationData['locale'], $translationData['name']);
            $translation->setDescription($translationData['description']);
            $group->addTranslation($translation);
        }

        $this->attributeGroupRepository->save($group);

        $this->domainEventCollector->collect(new AttributeGroupRestoredEvent($group, $data));

        return $group;
    }

    public function getConfiguration(): RestoreConfiguration
    {
        return new RestoreConfiguration(null, AttributeGroupAdmin::EDIT_TABS_VIEW, ['uuid' => 'id']);
    }
}
