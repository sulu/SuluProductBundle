<?php

declare(strict_types=1);

namespace Sulu\Product\Domain\Model;

class AttributeTranslation implements AttributeTranslationInterface
{
    protected int $id;

    protected string $locale;

    protected string $name;

    protected AttributeInterface $attribute;

    public function __construct(AttributeInterface $attribute, string $locale, string $name)
    {
        $this->attribute = $attribute;
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

    public function getAttribute(): AttributeInterface
    {
        return $this->attribute;
    }
}
