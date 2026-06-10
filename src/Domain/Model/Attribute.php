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

class Attribute implements AttributeInterface
{
    protected int $id;

    protected ?string $uuid = null;

    protected ?string $externalIdentifier = null;

    protected string $key;

    protected string $type = self::TYPE_NUMBER;

    /** @var Collection<int, AttributeTranslationInterface> */
    protected Collection $translations;

    /** @var Collection<int, AttributeOptionInterface> */
    protected Collection $options;

    protected int $position = 0;

    protected AttributeGroupInterface $attributeGroup;

    public function __construct(AttributeGroupInterface $attributeGroup)
    {
        $this->translations = new ArrayCollection();
        $this->options = new ArrayCollection();
        $this->attributeGroup = $attributeGroup;
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

    public function getTranslation(string $locale): ?AttributeTranslationInterface
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('locale', $locale));

        /** @var AttributeTranslationInterface|false $translation */
        $translation = $this->translations->matching($criteria)->first();

        return $translation ?: null;
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
        $options = $this->options->toArray();
        \usort($options, static fn (AttributeOptionInterface $a, AttributeOptionInterface $b) => $a->getPosition() <=> $b->getPosition());

        return $options;
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getAttributeGroup(): AttributeGroupInterface
    {
        return $this->attributeGroup;
    }

    public function setAttributeGroup(AttributeGroupInterface $attributeGroup): self
    {
        $this->attributeGroup = $attributeGroup;

        return $this;
    }
}
