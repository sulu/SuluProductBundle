<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Application\Message;

class RemoveProductTranslationMessage
{
    /**
     * @param array{uuid?: string} $identifier
     */
    public function __construct(
        private readonly array $identifier,
        private readonly string $locale,
    ) {
    }

    /**
     * @return array{
     *     uuid?: string
     * }
     */
    public function getIdentifier(): array
    {
        return $this->identifier;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
