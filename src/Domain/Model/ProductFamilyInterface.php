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

interface ProductFamilyInterface
{
    public const RESOURCE_KEY = 'product_families';
    public const FORM_KEY = 'product_family_details';
    public const LIST_KEY = 'product_families';

    public function getId(): int;

    public function getUuid(): ?string;

    public function setUuid(string $uuid): self;

    public function getExternalIdentifier(): ?string;

    public function setExternalIdentifier(?string $externalIdentifier): self;

    public function getTranslation(string $locale): ?ProductFamilyTranslationInterface;

    public function addTranslation(ProductFamilyTranslationInterface $translation): self;

    public function removeTranslation(ProductFamilyTranslationInterface $translation): self;

    /** @return ProductFamilyAttributeInterface[] */
    public function getFamilyAttributes(): array;

    public function addFamilyAttribute(ProductFamilyAttributeInterface $familyAttribute): self;

    public function removeFamilyAttribute(ProductFamilyAttributeInterface $familyAttribute): self;
}
