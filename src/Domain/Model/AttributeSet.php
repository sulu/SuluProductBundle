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

class AttributeSet implements AttributeSetInterface
{
    protected int $id;

    protected ?string $uuid = null;

    protected ?string $externalIdentifier = null;

    protected ?string $currentLocale = null;

    /** @var Collection<int, AttributeSetTranslationInterface> */
    protected Collection $translations;

    /** @var Collection<int, AttributeSetAttributeInterface> */
    protected Collection $setAttributes;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->setAttributes = new ArrayCollection();
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

    public function getTranslation(?string $locale = null): ?AttributeSetTranslationInterface
    {
        $locale ??= $this->currentLocale;

        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function addTranslation(AttributeSetTranslationInterface $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
        }

        return $this;
    }

    public function removeTranslation(AttributeSetTranslationInterface $translation): self
    {
        $this->translations->removeElement($translation);

        return $this;
    }

    public function getSetAttributes(): array
    {
        $setAttributes = $this->setAttributes->toArray();
        \usort($setAttributes, static fn (AttributeSetAttributeInterface $a, AttributeSetAttributeInterface $b) => $a->getPosition() <=> $b->getPosition());

        return $setAttributes;
    }

    public function addSetAttribute(AttributeSetAttributeInterface $setAttribute): self
    {
        if (!$this->setAttributes->contains($setAttribute)) {
            $this->setAttributes->add($setAttribute);
        }

        return $this;
    }

    public function removeSetAttribute(AttributeSetAttributeInterface $setAttribute): self
    {
        $this->setAttributes->removeElement($setAttribute);

        return $this;
    }
}
