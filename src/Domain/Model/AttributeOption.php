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

class AttributeOption implements AttributeOptionInterface
{
    protected int $id;

    protected ?string $uuid = null;

    protected string $key;

    protected AttributeInterface $attribute;

    protected ?string $currentLocale = null;

    /** @var Collection<int, AttributeOptionTranslationInterface> */
    protected Collection $translations;

    public function __construct(AttributeInterface $attribute, string $key)
    {
        $this->attribute = $attribute;
        $this->key = $key;
        $this->translations = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
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

    public function getAttribute(): AttributeInterface
    {
        return $this->attribute;
    }

    public function getTranslation(?string $locale = null): ?AttributeOptionTranslationInterface
    {
        $locale ??= $this->currentLocale;
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function addTranslation(AttributeOptionTranslationInterface $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
        }

        return $this;
    }

    public function removeTranslation(AttributeOptionTranslationInterface $translation): self
    {
        $this->translations->removeElement($translation);

        return $this;
    }
}
