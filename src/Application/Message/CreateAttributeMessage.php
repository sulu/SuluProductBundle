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
 * @phpstan-type AttributeOptionMessageData array{
 *     type?: string,
 *     key: string,
 *     name: string,
 * }
 * @phpstan-type CreateAttributeMessageData array{
 *     locale: string,
 *     key: string,
 *     type: string,
 *     name: string,
 *     description?: string|null,
 *     options?: list<AttributeOptionMessageData>|null,
 *     group: string,
 *     position?: int|null,
 * }
 */
class CreateAttributeMessage
{
    /**
     * @param CreateAttributeMessageData $data
     */
    public function __construct(private readonly array $data)
    {
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

    public function getGroup(): string
    {
        return $this->data['group'];
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
     * @return CreateAttributeMessageData
     */
    public function getData(): array
    {
        return $this->data;
    }
}
