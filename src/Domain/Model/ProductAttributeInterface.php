<?php

declare(strict_types=1);

namespace Sulu\Product\Domain\Model;

interface ProductAttributeInterface
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
}
