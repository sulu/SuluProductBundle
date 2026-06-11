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
 * @phpstan-type RemoveAttributeMessageIdentifier array{
 *     uuid?: string,
 *     key?: string,
 * }&array<string, mixed>
 */
class RemoveAttributeMessage
{
    /**
     * @param RemoveAttributeMessageIdentifier $identifier
     */
    public function __construct(
        private readonly array $identifier,
    ) {
    }

    /**
     * @return RemoveAttributeMessageIdentifier
     */
    public function getIdentifier(): array
    {
        return $this->identifier;
    }
}
