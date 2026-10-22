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
 * Publishes or unpublishes one component row of a configurable product.
 */
class ApplyProductComponentTransitionMessage
{
    /**
     * @param array{uuid?: string} $identifier the configurable product
     */
    public function __construct(
        private readonly array $identifier,
        private readonly string $locale,
        private readonly string $componentUuid,
        private readonly string $transitionName,
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

    public function getComponentUuid(): string
    {
        return $this->componentUuid;
    }

    public function getTransitionName(): string
    {
        return $this->transitionName;
    }
}
