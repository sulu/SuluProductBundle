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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Attribute implements AttributeInterface
{
    protected int $id;

    protected ?string $uuid = null;

    protected string $key;

    protected string $type = self::TYPE_NUMBER;

    protected ?string $currentLocale = null;

    /** @var Collection<int, AttributeTranslationInterface> */
    protected Collection $translations;

    /** @var Collection<int, AttributeOptionInterface> */
    protected Collection $options;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->options = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setCurrentLocale(string $locale): self
    {
        $this->currentLocale = $locale;

        return $this;
    }

    public function getTranslation(?string $locale = null): ?AttributeTranslationInterface
    {
        $locale ??= $this->currentLocale;

        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function addTranslation(AttributeTranslationInterface $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
        }

        return $this;
    }

    public function removeTranslation(AttributeTranslationInterface $translation): self
    {
        $this->translations->removeElement($translation);

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options->toArray();
    }

    public function getOption(string $key): ?AttributeOptionInterface
    {
        foreach ($this->options as $option) {
            if ($option->getKey() === $key) {
                return $option;
            }
        }

        return null;
    }

    public function addOption(AttributeOptionInterface $option): self
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
        }

        return $this;
    }

    public function removeOption(AttributeOptionInterface $option): self
    {
        $this->options->removeElement($option);

        return $this;
    }
}
