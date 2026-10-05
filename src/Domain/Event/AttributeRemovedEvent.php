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

namespace Sulu\Product\Domain\Event;

use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;

class AttributeRemovedEvent extends DomainEvent
{
    public function __construct(
        private string $attributeUuid,
        private ?string $attributeTitle,
        private ?string $attributeTitleLocale = null,
    ) {
        parent::__construct();
    }

    public function getEventType(): string
    {
        return 'removed';
    }

    public function getResourceKey(): string
    {
        return AttributeInterface::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return $this->attributeUuid;
    }

    public function getResourceTitle(): ?string
    {
        return $this->attributeTitle;
    }

    public function getResourceTitleLocale(): ?string
    {
        return $this->attributeTitleLocale;
    }

    public function getResourceSecurityContext(): ?string
    {
        return AttributeAdmin::SECURITY_CONTEXT;
    }
}
