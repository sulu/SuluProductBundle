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

class AttributeGroup implements AttributeGroupInterface
{
    protected int $id;

    protected ?string $uuid = null;

    protected ?string $externalIdentifier = null;

    protected ?string $currentLocale = null;

    /** @var Collection<int, AttributeGroupTranslationInterface> */
    protected Collection $translations;

    /** @var Collection<int, AttributeGroupAttributeInterface> */
    protected Collection $groupAttributes;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->groupAttributes = new ArrayCollection();
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

    public function getExternalIdentifier(): ?string
    {
        return $this->externalIdentifier;
    }

    public function setExternalIdentifier(?string $externalIdentifier): self
    {
        $this->externalIdentifier = $externalIdentifier;

        return $this;
    }

    public function setCurrentLocale(string $locale): self
    {
        $this->currentLocale = $locale;

        return $this;
    }

    public function getTranslation(?string $locale = null): ?AttributeGroupTranslationInterface
    {
        $locale ??= $this->currentLocale;

        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function addTranslation(AttributeGroupTranslationInterface $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
        }

        return $this;
    }

    public function removeTranslation(AttributeGroupTranslationInterface $translation): self
    {
        $this->translations->removeElement($translation);

        return $this;
    }

    public function getGroupAttributes(): array
    {
        $groupAttributes = $this->groupAttributes->toArray();
        \usort($groupAttributes, static fn (AttributeGroupAttributeInterface $a, AttributeGroupAttributeInterface $b) => $a->getPosition() <=> $b->getPosition());

        return $groupAttributes;
    }

    public function addGroupAttribute(AttributeGroupAttributeInterface $groupAttribute): self
    {
        if (!$this->groupAttributes->contains($groupAttribute)) {
            $this->groupAttributes->add($groupAttribute);
        }

        return $this;
    }

    public function removeGroupAttribute(AttributeGroupAttributeInterface $groupAttribute): self
    {
        $this->groupAttributes->removeElement($groupAttribute);

        return $this;
    }
}
