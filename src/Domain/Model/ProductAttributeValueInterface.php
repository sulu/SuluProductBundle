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

interface ProductAttributeValueInterface
{
    public function getId(): int;

    public function getAttributeKey(): string;

    public function getAttributeOptionKey(): ?string;

    public function setAttributeOptionKey(?string $key): self;

    public function getNumber(): ?float;

    public function setNumber(?float $number): self;

    public function getText(): ?string;

    public function setText(?string $text): self;

    public function getJson(): mixed;

    public function setJson(mixed $json): self;

    public function getValue(): mixed;

    public function getProduct(): ProductInterface;

    public function getAttribute(): AttributeInterface;

    public function getAttributeOption(): ?AttributeOptionInterface;

    public function setAttributeOption(?AttributeOptionInterface $option): self;

    public function getProductFamilyAttribute(): ?ProductFamilyAttributeInterface;

    public function setProductFamilyAttribute(?ProductFamilyAttributeInterface $productFamilyAttribute): self;
}
