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

interface AttributeSetInterface
{
    public const RESOURCE_KEY = 'attribute_sets';
    public const FORM_KEY = 'attribute_set_details';
    public const LIST_KEY = 'attribute_sets';

    public function getId(): int;

    public function getUuid(): ?string;

    public function setUuid(string $uuid): self;

    public function getExternalIdentifier(): ?string;

    public function setExternalIdentifier(?string $externalIdentifier): self;

    public function setCurrentLocale(string $locale): self;

    public function getTranslation(?string $locale = null): ?AttributeSetTranslationInterface;

    public function addTranslation(AttributeSetTranslationInterface $translation): self;

    public function removeTranslation(AttributeSetTranslationInterface $translation): self;

    /** @return AttributeSetAttributeInterface[] */
    public function getSetAttributes(): array;

    public function addSetAttribute(AttributeSetAttributeInterface $setAttribute): self;

    public function removeSetAttribute(AttributeSetAttributeInterface $setAttribute): self;
}
