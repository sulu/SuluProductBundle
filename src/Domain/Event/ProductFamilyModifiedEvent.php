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
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductFamilyAdmin;

class ProductFamilyModifiedEvent extends DomainEvent
{
    /**
     * @param mixed[] $payload
     */
    public function __construct(
        private ProductFamilyInterface $productFamily,
        private string $locale,
        private array $payload,
    ) {
        parent::__construct();
    }

    public function getProductFamily(): ProductFamilyInterface
    {
        return $this->productFamily;
    }

    public function getEventType(): string
    {
        return 'modified';
    }

    public function getEventPayload(): ?array
    {
        return $this->payload;
    }

    public function getResourceKey(): string
    {
        return ProductFamilyInterface::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return (string) $this->productFamily->getUuid();
    }

    public function getResourceLocale(): ?string
    {
        return $this->locale;
    }

    public function getResourceTitle(): ?string
    {
        return $this->productFamily->getTranslation($this->locale)?->getName();
    }

    public function getResourceTitleLocale(): ?string
    {
        return $this->locale;
    }

    public function getResourceSecurityContext(): ?string
    {
        return ProductFamilyAdmin::SECURITY_CONTEXT;
    }
}
