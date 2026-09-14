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

namespace Sulu\Product\Infrastructure\Sulu\Search;

use Sulu\Product\Domain\Event\AttributeCreatedEvent;
use Sulu\Product\Domain\Model\AttributeInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A number or date attribute is a field of the website index, and the index only takes fields it
 * was created with, so the index is rebuilt when one is created.
 *
 * @internal this class is internal no backwards compatibility promise is given for this class
 */
final class WebsiteProductSchemaListener
{
    private const SCHEMA_TYPES = [
        AttributeInterface::TYPE_NUMBER,
        AttributeInterface::TYPE_DATE,
    ];

    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function onAttributeCreated(AttributeCreatedEvent $event): void
    {
        if (!\in_array($event->getAttribute()->getType(), self::SCHEMA_TYPES, true)) {
            return;
        }

        $this->messageBus->dispatch(new RebuildWebsiteIndexMessage());
    }
}
