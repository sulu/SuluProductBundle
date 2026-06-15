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
 * @phpstan-import-type AttributeOptionMessageData from CreateAttributeMessage
 *
 * @phpstan-type ModifyAttributeMessageIdentifier array{
 *     uuid?: string,
 *     key?: string,
 * }
 * @phpstan-type ModifyAttributeMessageData array{
 *     locale: string,
 *     key: string,
 *     type: string,
 *     name: string,
 *     description?: string|null,
 *     options?: list<AttributeOptionMessageData>|null,
 *     position?: int|null,
 * }
 */
class ModifyAttributeMessage
{
    /**
     * @param ModifyAttributeMessageIdentifier $identifier
     * @param ModifyAttributeMessageData $data
     */
    public function __construct(
        private readonly array $identifier,
        private readonly array $data,
    ) {
    }

    /**
     * @return ModifyAttributeMessageIdentifier
     */
    public function getIdentifier(): array
    {
        return $this->identifier;
    }

    public function getLocale(): string
    {
        return $this->data['locale'];
    }

    public function getKey(): string
    {
        return $this->data['key'];
    }

    public function getType(): string
    {
        return $this->data['type'];
    }

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getDescription(): ?string
    {
        return $this->data['description'] ?? null;
    }

    public function getPosition(): ?int
    {
        return $this->data['position'] ?? null;
    }

    /**
     * @return list<AttributeOptionMessageData>|null
     */
    public function getOptions(): ?array
    {
        return $this->data['options'] ?? null;
    }

    /**
     * @return ModifyAttributeMessageData
     */
    public function getData(): array
    {
        return $this->data;
    }
}
