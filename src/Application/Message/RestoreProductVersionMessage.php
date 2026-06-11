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

class RestoreProductVersionMessage
{
    /**
     * @param array{
     *     uuid?: string,
     * } $productIdentifier
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $productIdentifier,
        private readonly int $version,
        private readonly string $locale,
        private readonly array $options = [],
    ) {
    }

    /**
     * @return array{
     *     uuid?: string,
     * }
     */
    public function getProductIdentifier(): array
    {
        return $this->productIdentifier;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
