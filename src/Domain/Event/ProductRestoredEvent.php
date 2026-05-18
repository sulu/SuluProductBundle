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

class ProductRestoredEvent extends DomainEvent
{
    /**
     * @param array{
     *     locales?: string[]
     * } $context
     * @param mixed[] $payload
     */
    public function __construct(
        private ProductInterface $product,
        private ?string $productTitle,
        private array $context,
        private array $payload,
    ) {
        parent::__construct();
    }

    public function getProduct(): ProductInterface
    {
        return $this->product;
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
        return ProductInterface::RESOURCE_KEY;
    }

    public function getResourceId(): string
    {
        return (string) $this->product->getUuid();
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
