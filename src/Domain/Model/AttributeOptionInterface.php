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

interface AttributeOptionInterface
{
    public function getId(): int;

    public function getUuid(): ?string;

    public function getKey(): string;

    public function setKey(string $key): self;

    public function getPosition(): int;

    public function setPosition(int $position): self;

    public function getAttribute(): AttributeInterface;

    public function getTranslation(string $locale): ?AttributeOptionTranslationInterface;

    public function addTranslation(AttributeOptionTranslationInterface $translation): self;

    public function removeTranslation(AttributeOptionTranslationInterface $translation): self;
}
