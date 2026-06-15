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
 * @phpstan-type ModifyProductMessageIdentifier array{
 *     uuid?: string,
 * }
 * @phpstan-type ModifyProductMessageData array{
 *     locale: string,
 *     code?: string,
 * }
 */
class ModifyProductMessage
{
    /**
     * @param ModifyProductMessageIdentifier $identifier
     * @param ModifyProductMessageData $data
     */
    public function __construct(
        private readonly array $identifier,
        private readonly array $data,
    ) {
    }

    /**
     * @return ModifyProductMessageIdentifier
     */
    public function getIdentifier(): array
    {
        return $this->identifier;
    }

    public function getLocale(): string
    {
        return $this->data['locale'];
    }

    /**
     * @return ModifyProductMessageData
     */
    public function getData(): array
    {
        return $this->data;
    }
}
