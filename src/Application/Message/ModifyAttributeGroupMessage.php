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
 * @phpstan-import-type AttributeGroupAttributeMessageData from CreateAttributeGroupMessage
 *
 * @phpstan-type ModifyAttributeGroupMessageIdentifier array{
 *     uuid: string,
 * }
 * @phpstan-type ModifyAttributeGroupMessageData array{
 *     locale: string,
 *     name: string,
 *     description?: string|null,
 *     attributes?: list<AttributeGroupAttributeMessageData>|null,
 * }
 */
class ModifyAttributeGroupMessage
{
    /**
     * @param ModifyAttributeGroupMessageIdentifier $identifier
     * @param ModifyAttributeGroupMessageData $data
     */
    public function __construct(
        private readonly array $identifier,
        private readonly array $data,
    ) {
    }

    /**
     * @return ModifyAttributeGroupMessageIdentifier
     */
    public function getIdentifier(): array
    {
        return $this->identifier;
    }

    public function getUuid(): string
    {
        return $this->identifier['uuid'];
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
     * @return ModifyAttributeGroupMessageData
     */
    public function getData(): array
    {
        return $this->data;
    }
}
