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

class ProductFamilyRemovedEvent extends DomainEvent
{
    public function __construct(
        private string $productFamilyUuid,
        private ?string $productFamilyTitle,
        private ?string $productFamilyTitleLocale = null,
    ) {
        parent::__construct();
    }

    public function getEventType(): string
    {
        return 'removed';
    }

    public function getResourceKey(): string
    {
        return ProductFamilyInterface::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return $this->productFamilyUuid;
    }

    public function getResourceTitle(): ?string
    {
        return $this->productFamilyTitle;
    }

    public function getResourceTitleLocale(): ?string
    {
        return $this->productFamilyTitleLocale;
    }

    public function getResourceSecurityContext(): ?string
    {
        return ProductFamilyAdmin::SECURITY_CONTEXT;
    }
}
