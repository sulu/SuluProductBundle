<?php

declare(strict_types=1);

namespace Sulu\Product\Domain\Model;

interface ProductTranslationInterface
{
    public function getId(): int;

    public function getLocale(): string;

    public function setLocale(string $locale): self;

    public function getName(): string;

    public function setName(string $name): self;

    public function getProduct(): ProductInterface;
}
