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
use Sulu\Product\Application\Message\CreateAttributeGroupMessage;
use Sulu\Product\Domain\Event\AttributeGroupCreatedEvent;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;

final class CreateAttributeGroupMessageHandler
{
    public function __construct(
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
        private DomainEventCollectorInterface $domainEventCollector,
    ) {
    }

    public function __invoke(CreateAttributeGroupMessage $message): AttributeGroupInterface
    {
        $group = $this->attributeGroupRepository->createNew();
        $group->setDefaultLocale($message->getLocale());

        $translation = new AttributeGroupTranslation($group, $message->getLocale(), $message->getName());

        if (null !== $message->getDescription()) {
            $translation->setDescription($message->getDescription());
        }

        $group->addTranslation($translation);

        $this->attributeGroupRepository->save($group);

        $this->domainEventCollector->collect(
            new AttributeGroupCreatedEvent($group, $message->getLocale(), $message->getData()),
        );

        return $group;
    }
}
