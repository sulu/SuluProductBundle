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

class ModifyAttributeSetMessage
{
    private string $uuid;

    private string $locale;

    private string $name;

    private ?string $description;

    /**
     * @var array<array{attribute: string, required?: bool}>
     */
    private array $attributes;

    /**
     * @param array<array{attribute: string, required?: bool}> $attributes
     */
    public function __construct(
        string $uuid,
        string $locale,
        string $name,
        ?string $description = null,
        array $attributes = [],
    ) {
        $this->uuid = $uuid;
        $this->locale = $locale;
        $this->name = $name;
        $this->description = $description;
        $this->attributes = $attributes;
    }

    public function getUuid(): string
    {
        return $this->uuid;
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
     * @return array<array{attribute: string, required?: bool}>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
