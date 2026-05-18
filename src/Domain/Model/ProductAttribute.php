<?php

declare(strict_types=1);

namespace Sulu\Product\Domain\Model;

class ProductAttribute implements ProductAttributeInterface
{
    protected int $id;

    protected string $attributeKey;

    protected ?string $attributeOptionKey = null;

    protected ?float $number = null;

    protected ?string $text = null;

    protected mixed $json = null;

    protected ProductInterface $product;

    protected AttributeInterface $attribute;

    protected ?AttributeOptionInterface $attributeOption = null;

    public function __construct(
        ProductInterface $product,
        AttributeInterface $attribute,
        string $attributeKey,
    ) {
        $this->product = $product;
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

    public function getJson(): mixed
    {
        return $this->json;
    }

    public function setJson(mixed $json): self
    {
        $this->json = $json;
        return $this;
    }

    public function getValue(): mixed
    {
        return $this->attributeOptionKey
            ?? $this->number
            ?? $this->text
            ?? $this->json;
    }

    public function getProduct(): ProductInterface
    {
        return $this->product;
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
}
