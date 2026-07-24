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

class ProductAttributeValue implements ProductAttributeValueInterface
{
    protected int $id;

    protected string $attributeKey;

    protected ?string $attributeOptionKey = null;

    protected ?float $number = null;

    protected ?string $text = null;

    protected ProductDimensionContentInterface $productDimensionContent;

    protected AttributeInterface $attribute;

    protected ?AttributeOptionInterface $attributeOption = null;

    protected ?ProductFamilyAttributeInterface $productFamilyAttribute = null;

    public function __construct(
        ProductDimensionContentInterface $productDimensionContent,
        AttributeInterface $attribute,
        string $attributeKey,
    ) {
        $this->productDimensionContent = $productDimensionContent;
        $this->attribute = $attribute;
        $this->attributeKey = $attributeKey;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAttributeKey(): string
    {
        return $this->attributeKey;
    }

    public function getAttributeOptionKey(): ?string
    {
        return $this->attributeOptionKey;
    }

    public function setAttributeOptionKey(?string $key): self
    {
        $this->attributeOptionKey = $key;

        return $this;
    }

    public function getNumber(): ?float
    {
        return $this->number;
    }

    public function setNumber(?float $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getValue(): mixed
    {
        return $this->attributeOptionKey
            ?? $this->number
            ?? $this->text;
    }

    public function getProductDimensionContent(): ProductDimensionContentInterface
    {
        return $this->productDimensionContent;
    }

    public function getAttribute(): AttributeInterface
    {
        return $this->attribute;
    }

    public function getAttributeOption(): ?AttributeOptionInterface
    {
        return $this->attributeOption;
    }

    public function setAttributeOption(?AttributeOptionInterface $option): self
    {
        $this->attributeOption = $option;

        return $this;
    }

    public function getProductFamilyAttribute(): ?ProductFamilyAttributeInterface
    {
        return $this->productFamilyAttribute;
    }

    public function setProductFamilyAttribute(?ProductFamilyAttributeInterface $productFamilyAttribute): self
    {
        $this->productFamilyAttribute = $productFamilyAttribute;

        return $this;
    }
}
