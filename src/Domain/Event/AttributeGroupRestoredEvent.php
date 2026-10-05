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

class AttributeGroupRestoredEvent extends DomainEvent
{
    /**
     * @param mixed[] $payload
     */
    public function __construct(
        private AttributeGroupInterface $attributeGroup,
        private array $payload,
    ) {
        parent::__construct();
    }

    public function getAttributeGroup(): AttributeGroupInterface
    {
        return $this->attributeGroup;
    }

    public function getEventType(): string
    {
        return 'restored';
    }

    public function getEventPayload(): ?array
    {
        return $this->payload;
    }

    public function getResourceKey(): string
    {
        return AttributeGroupInterface::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return (string) $this->attributeGroup->getUuid();
    }

    public function getResourceTitle(): ?string
    {
        $locale = $this->getResourceTitleLocale();

        return null !== $locale ? $this->attributeGroup->getTranslation($locale)?->getName() : null;
    }

    public function getResourceTitleLocale(): ?string
    {
        return $this->attributeGroup->getDefaultLocale();
    }

    public function getResourceSecurityContext(): ?string
    {
        return AttributeGroupAdmin::SECURITY_CONTEXT;
    }
}
