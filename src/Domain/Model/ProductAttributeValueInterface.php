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
    /**
     * The value key of an attribute stored in a single row.
     */
    public const DEFAULT_VALUE_KEY = 'value';

    public function getId(): int;

    public function getAttributeKey(): string;

    /**
     * Names this row among the rows of one attribute, e.g. "from" and "to" of a range.
     */
    public function getValueKey(): string;

    public function getAttributeOptionKey(): ?string;

    public function getNumber(): ?float;

    public function setNumber(?float $number): self;

    public function getText(): ?string;

    public function setText(?string $text): self;

    public function getProductDimensionContent(): ProductDimensionContentInterface;

    public function getAttribute(): AttributeInterface;

    public function getAttributeOption(): ?AttributeOptionInterface;

    public function setAttributeOption(?AttributeOptionInterface $option): self;

    public function getProductFamilyAttribute(): ?ProductFamilyAttributeInterface;

    public function setProductFamilyAttribute(?ProductFamilyAttributeInterface $productFamilyAttribute): self;
}
