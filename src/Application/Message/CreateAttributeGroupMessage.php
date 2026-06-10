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

class CreateAttributeGroupMessage
{
    private string $locale;

    private string $name;

    private ?string $description;

    /**
     * @var array<array{attribute: string}>
     */
    private array $attributes;

    /**
     * @param array<array{attribute: string}> $attributes
     */
    public function __construct(
        string $locale,
        string $name,
        ?string $description = null,
        array $attributes = [],
    ) {
        $this->locale = $locale;
        $this->name = $name;
        $this->description = $description;
        $this->attributes = $attributes;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<array{attribute: string}>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
