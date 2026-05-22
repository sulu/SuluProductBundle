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
use Sulu\Content\Domain\Model\DimensionContentCollection;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;

class ProductTranslationRestoredEvent extends DomainEvent
{
    /**
     * @param mixed[] $payload
     */
    public function __construct(
        private ProductInterface $product,
        private string $locale,
        private array $payload
    ) {
        parent::__construct();
    }

    public function getProduct(): ProductInterface
    {
        return $this->product;
    }

    public function getEventType(): string
    {
        return 'translation_restored';
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

    public function getResourceLocale(): ?string
    {
        return $this->locale;
    }

    public function getResourceTitle(): ?string
    {
        $dimensionContentCollection = new DimensionContentCollection($this->product->getDimensionContents(), [], ProductDimensionContent::class);

        return $dimensionContentCollection->getDimensionContent(['locale' => $this->locale])?->getTitle();
    }

    public function getResourceTitleLocale(): ?string
    {
        return $this->locale;
    }

    public function getResourceSecurityContext(): ?string
    {
        return ProductAdmin::SECURITY_CONTEXT;
    }
}
