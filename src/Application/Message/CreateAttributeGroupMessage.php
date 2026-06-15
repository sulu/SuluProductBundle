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
 * @phpstan-type AttributeGroupAttributeMessageData array{
 *     attribute: string,
 * }
 * @phpstan-type CreateAttributeGroupMessageData array{
 *     locale: string,
 *     name: string,
 *     description?: string|null,
 *     attributes?: list<AttributeGroupAttributeMessageData>|null,
 * }
 */
class CreateAttributeGroupMessage
{
    /**
     * @param CreateAttributeGroupMessageData $data
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
     * @return list<AttributeGroupAttributeMessageData>
     */
    public function getAttributes(): array
    {
        return $this->data['attributes'] ?? [];
    }

    /**
     * @return CreateAttributeGroupMessageData
     */
    public function getData(): array
    {
        return $this->data;
    }
}
