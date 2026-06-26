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
use Doctrine\Common\Collections\Criteria;
use Sulu\Component\Persistence\Model\AuditableTrait;

class ProductFamily implements ProductFamilyInterface
{
    use AuditableTrait;

    protected int $id;

    protected ?string $uuid = null;

    protected ?string $externalIdentifier = null;

    protected ?string $defaultLocale = null;

    /** @var Collection<int, ProductFamilyTranslationInterface> */
    protected Collection $translations;

    /** @var Collection<int, ProductFamilyAttributeInterface> */
    protected Collection $familyAttributes;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->familyAttributes = new ArrayCollection();
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

    public function getDefaultLocale(): ?string
    {
        return $this->defaultLocale;
    }

    public function setDefaultLocale(string $defaultLocale): self
    {
        $this->defaultLocale = $defaultLocale;

        return $this;
    }

    public function getTranslation(string $locale): ?ProductFamilyTranslationInterface
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('locale', $locale));

        /** @var ProductFamilyTranslationInterface|false $translation */
        $translation = $this->translations->matching($criteria)->first();

        return $translation ?: null;
    }

    public function getTranslations(): iterable
    {
        return $this->translations;
    }

    public function addTranslation(ProductFamilyTranslationInterface $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
        }

        return $this;
    }

    public function removeTranslation(ProductFamilyTranslationInterface $translation): self
    {
        $this->translations->removeElement($translation);

        return $this;
    }

    public function getFamilyAttributes(): array
    {
        return $this->familyAttributes->toArray();
    }

    public function addFamilyAttribute(ProductFamilyAttributeInterface $familyAttribute): self
    {
        if (!$this->familyAttributes->contains($familyAttribute)) {
            $this->familyAttributes->add($familyAttribute);
        }

        return $this;
    }

    public function removeFamilyAttribute(ProductFamilyAttributeInterface $familyAttribute): self
    {
        $this->familyAttributes->removeElement($familyAttribute);

        return $this;
    }
}
