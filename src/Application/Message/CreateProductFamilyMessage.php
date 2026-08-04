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

namespace Sulu\Product\Application\Message;

/**
 * @phpstan-type ProductFamilyAttributeMessageData array{
 *     enabled: bool,
 *     required: bool,
 *     variantSpecific: bool,
 * }
 * @phpstan-type CreateProductFamilyMessageData array{
 *     locale: string,
 *     name: string,
 *     description?: string|null,
 *     attributes?: array<int, ProductFamilyAttributeMessageData>|null,
 * }
 */
class CreateProductFamilyMessage
{
    /**
     * @param CreateProductFamilyMessageData $data
     */
    public function __construct(private readonly array $data)
    {
    }

    public function getLocale(): string
    {
        return $this->data['locale'];
    }

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getDescription(): ?string
    {
        return $this->data['description'] ?? null;
    }

    /**
     * @return array<int, ProductFamilyAttributeMessageData>
     */
    public function getAttributes(): array
    {
        return $this->data['attributes'] ?? [];
    }

    /**
     * @return CreateProductFamilyMessageData
     */
    public function getData(): array
    {
        return $this->data;
    }
}
