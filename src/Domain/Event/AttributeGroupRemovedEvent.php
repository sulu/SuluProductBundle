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
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeGroupAdmin;

class AttributeGroupRemovedEvent extends DomainEvent
{
    public function __construct(
        private string $attributeGroupUuid,
        private ?string $attributeGroupTitle,
        private ?string $attributeGroupTitleLocale = null,
    ) {
        parent::__construct();
    }

    public function getEventType(): string
    {
        return 'removed';
    }

    public function getResourceKey(): string
    {
        return AttributeGroupInterface::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return $this->attributeGroupUuid;
    }

    public function getResourceTitle(): ?string
    {
        return $this->attributeGroupTitle;
    }

    public function getResourceTitleLocale(): ?string
    {
        return $this->attributeGroupTitleLocale;
    }

    public function getResourceSecurityContext(): ?string
    {
        return AttributeGroupAdmin::SECURITY_CONTEXT;
    }
}
