<?php

declare(strict_types=1);

namespace Sulu\Product\Domain\Model;

interface AttributeOptionInterface
{
    public function getId(): int;

    public function getUuid(): ?string;

    public function getKey(): string;

    public function setKey(string $key): self;

    public function getAttribute(): AttributeInterface;

    public function getTranslation(?string $locale = null): ?AttributeOptionTranslationInterface;

    public function addTranslation(AttributeOptionTranslationInterface $translation): self;

    public function removeTranslation(AttributeOptionTranslationInterface $translation): self;
}
