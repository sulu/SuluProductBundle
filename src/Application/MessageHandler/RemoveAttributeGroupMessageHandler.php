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

namespace Sulu\Product\Application\MessageHandler;

use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Product\Application\Message\RemoveAttributeGroupMessage;
use Sulu\Product\Domain\Event\AttributeGroupRemovedEvent;
use Sulu\Product\Domain\Exception\AttributeGroupNotEmptyException;
use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class RemoveAttributeGroupMessageHandler
{
    public function __construct(
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
        private AttributeRepositoryInterface $attributeRepository,
        private DomainEventCollectorInterface $domainEventCollector,
        private TrashManagerInterface $trashManager,
    ) {
    }

    public function __invoke(RemoveAttributeGroupMessage $message): void
    {
        $group = $this->attributeGroupRepository->findOneBy(['uuid' => $message->getUuid()]);

        if (null === $group) {
            throw new AttributeGroupNotFoundException(['uuid' => $message->getUuid()]);
        }

        $attributeCount = $this->attributeRepository->countBy(['group' => $group]);
        if ($attributeCount > 0) {
            throw new AttributeGroupNotEmptyException($message->getUuid(), $attributeCount);
        }

        $this->trashManager->store(AttributeGroupInterface::RESOURCE_KEY, $group);

        $this->attributeGroupRepository->remove($group);

        $titleLocale = $group->getDefaultLocale();
        $this->domainEventCollector->collect(new AttributeGroupRemovedEvent(
            $message->getUuid(),
            null !== $titleLocale ? $group->getTranslation($titleLocale)?->getName() : null,
            $titleLocale,
        ));
    }
}
