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

class ProductRemovedEvent extends DomainEvent
{
    /**
     * @param array{
     *     locales?: string[]
     * } $context
     */
    public function __construct(
        private string $productId,
        private ?string $productTitle,
        private array $context = [],
    ) {
        parent::__construct();
    }

    public function getEventType(): string
    {
        return 'removed';
    }

    public function getEventContext(): array
    {
        return $this->context;
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

    public function getResourceSecurityContext(): ?string
    {
        return ProductAdmin::SECURITY_CONTEXT;
    }

    /**
     * @return string[]|null
     */
    public function getAllLocales(): ?array
    {
        return $this->context['locales'] ?? null;
    }
}
