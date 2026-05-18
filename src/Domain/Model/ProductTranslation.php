<?php

declare(strict_types=1);

namespace Sulu\Product\Domain\Model;

class ProductTranslation implements ProductTranslationInterface
{
    protected int $id;

    protected string $locale;

    protected string $name;

    protected ProductInterface $product;

    public function __construct(ProductInterface $product, string $locale, string $name)
    {
        $this->product = $product;
        $this->locale = $locale;
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getProduct(): ProductInterface
    {
        return $this->product;
    }
}
