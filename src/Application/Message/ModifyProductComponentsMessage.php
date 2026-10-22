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
 * Replaces the components of a configurable product without touching its workflow.
 */
class ModifyProductComponentsMessage
{
    /**
     * @param array{uuid?: string} $identifier
     * @param array<int, string> $components the uuids of the component products in their order
     */
    public function __construct(
        private readonly array $identifier,
        private readonly string $locale,
        private readonly array $components,
    ) {
    }

    /**
     * @return array{uuid?: string}
     */
    public function getIdentifier(): array
    {
        return $this->identifier;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return array<int, string>
     */
    public function getComponents(): array
    {
        return $this->components;
    }
}
