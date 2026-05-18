<?php

declare(strict_types=1);

namespace Sulu\Product\Domain\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sulu\Component\Persistence\Model\AuditableTrait;
use Sulu\Content\Domain\Model\ContentRichEntityTrait;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Symfony\Component\Uid\Uuid;

class Product implements ProductInterface
{
    /**
     * @phpstan-use ContentRichEntityTrait<ProductDimensionContentInterface>
     */
    use ContentRichEntityTrait;
    use AuditableTrait;

    protected string $uuid;

    protected ?string $code = null;

    protected ?string $currentLocale = null;

    /** @var Collection<int, ProductTranslationInterface> */
    protected Collection $translations;

    /** @var Collection<int, ProductAttributeInterface> */
    protected Collection $attributes;

    public function __construct(?string $uuid = null)
    {
        $this->uuid = $uuid ?: Uuid::v7()->toRfc4122();
        $this->initializeDimensionContents();
        $this->translations = new ArrayCollection();
        $this->attributes = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->uuid;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getCurrentLocale(): ?string
    {
        return $this->currentLocale;
    }

    public function setCurrentLocale(string $locale): self
    {
        $this->currentLocale = $locale;
        return $this;
    }

    public function getTranslation(?string $locale = null): ?ProductTranslationInterface
    {
        $locale ??= $this->currentLocale;
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }
        return null;
    }

    public function addTranslation(ProductTranslationInterface $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
        }
        return $this;
    }

    public function removeTranslation(ProductTranslationInterface $translation): self
    {
        $this->translations->removeElement($translation);
        return $this;
    }

    /**
     * @return Collection<int, ProductAttributeInterface>
     */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(ProductAttributeInterface $attribute): self
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes->add($attribute);
        }
        return $this;
    }

    public function removeAttribute(ProductAttributeInterface $attribute): self
    {
        $this->attributes->removeElement($attribute);
        return $this;
    }

    /**
     * @return ProductDimensionContentInterface
     */
    public function createDimensionContent(): DimensionContentInterface
    {
        return new ProductDimensionContent($this);
    }
}
