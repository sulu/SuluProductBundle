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

class AttributeCreatedEvent extends DomainEvent
{
    /**
     * @param mixed[] $payload
     */
    public function __construct(
        private AttributeInterface $attribute,
        private string $locale,
        private array $payload,
    ) {
        parent::__construct();
    }

    public function getAttribute(): AttributeInterface
    {
        return $this->attribute;
    }

    public function getEventType(): string
    {
        return 'created';
    }

    public function getEventPayload(): ?array
    {
        return $this->payload;
    }

    public function getResourceKey(): string
    {
        return AttributeInterface::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return (string) $this->attribute->getUuid();
    }

    public function getResourceLocale(): ?string
    {
        return $this->locale;
    }

    public function getResourceTitle(): ?string
    {
        return $this->attribute->getTranslation($this->locale)?->getName();
    }

    public function getResourceTitleLocale(): ?string
    {
        return $this->locale;
    }

    public function getResourceSecurityContext(): ?string
    {
        return AttributeAdmin::SECURITY_CONTEXT;
    }
}
