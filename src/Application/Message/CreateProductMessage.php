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
 * @phpstan-type CreateProductMessageData array{
 *     locale: string,
 *     productFamily: string,
 *     uuid?: string,
 *     code?: string,
 *     author?: int|null,
 *     authored?: string,
 * }
 */
class CreateProductMessage
{
    /**
     * @param CreateProductMessageData $data
     */
    public function __construct(private readonly array $data)
    {
    }

    public function getLocale(): string
    {
        return $this->data['locale'];
    }

    public function getProductFamily(): string
    {
        return $this->data['productFamily'];
    }

    public function getUuid(): ?string
    {
        return $this->data['uuid'] ?? null;
    }

    /**
     * @return CreateProductMessageData
     */
    public function getData(): array
    {
        return $this->data;
    }
}
