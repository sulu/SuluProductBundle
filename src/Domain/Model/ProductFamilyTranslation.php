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

namespace Sulu\Product\Domain\Model;

class ProductFamilyTranslation implements ProductFamilyTranslationInterface
{
    protected int $id;

    protected string $locale;

    protected string $name;

    protected ?string $description = null;

    protected ProductFamilyInterface $family;

    public function __construct(ProductFamilyInterface $family, string $locale, string $name)
    {
        $this->family = $family;
        $this->locale = $locale;
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getFamily(): ProductFamilyInterface
    {
        return $this->family;
    }
}
