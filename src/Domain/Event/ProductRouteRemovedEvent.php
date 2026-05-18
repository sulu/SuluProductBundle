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

use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;

class ProductRouteRemovedEvent extends DomainEvent
{
    public function __construct(
        private string $productId,
        private string $productTitle,
        private string $productTitleLocale,
        private string $route
    ) {
        parent::__construct();
    }

    public function getEventType(): string
    {
        return 'route_removed';
    }

    public function getEventContext(): array
    {
        return [
            'route' => $this->route,
        ];
    }

    public function getResourceKey(): string
    {
        return ProductInterface::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return $this->productId;
    }

    public function getResourceTitle(): ?string
    {
        return $this->productTitle;
    }

    public function getResourceTitleLocale(): ?string
    {
        return $this->productTitleLocale;
    }

    public function getResourceSecurityContext(): ?string
    {
        return ProductAdmin::SECURITY_CONTEXT;
    }
}
