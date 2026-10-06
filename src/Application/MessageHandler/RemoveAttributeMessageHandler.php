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
use Sulu\Product\Application\Message\RemoveAttributeMessage;
use Sulu\Product\Domain\Event\AttributeRemovedEvent;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class RemoveAttributeMessageHandler
{
    public function __construct(
        private AttributeRepositoryInterface $attributeRepository,
        private DomainEventCollectorInterface $domainEventCollector,
        private TrashManagerInterface $trashManager,
    ) {
    }

    public function __invoke(RemoveAttributeMessage $message): void
    {
        $attribute = $this->attributeRepository->getOneBy($message->getIdentifier());

        $this->trashManager->store(AttributeInterface::RESOURCE_KEY, $attribute);

        $this->attributeRepository->remove($attribute);

        $titleLocale = $attribute->getDefaultLocale();
        $this->domainEventCollector->collect(new AttributeRemovedEvent(
            (string) $attribute->getUuid(),
            null !== $titleLocale ? $attribute->getTranslation($titleLocale)?->getName() : null,
            $titleLocale,
        ));
    }
}
