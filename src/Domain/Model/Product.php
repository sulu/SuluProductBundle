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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
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

    protected ?string $externalIdentifier = null;

    protected ProductFamilyInterface $productFamily;

    /** @var Collection<int, ProductTranslationInterface> */
    protected Collection $translations;

    /** @var Collection<int, ProductAttributeValueInterface> */
    protected Collection $attributes;

    public function __construct(ProductFamilyInterface $productFamily, ?string $uuid = null)
    {
        $this->productFamily = $productFamily;
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

    public function getExternalIdentifier(): ?string
    {
        return $this->externalIdentifier;
    }

    public function setExternalIdentifier(?string $externalIdentifier): self
    {
        $this->externalIdentifier = $externalIdentifier;

        return $this;
    }

    public function getProductFamily(): ProductFamilyInterface
    {
        return $this->productFamily;
    }

    public function setProductFamily(ProductFamilyInterface $productFamily): self
    {
        $this->productFamily = $productFamily;

        return $this;
    }

    public function getTranslation(string $locale): ?ProductTranslationInterface
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('locale', $locale));

        /** @var ProductTranslationInterface|false $translation */
        $translation = $this->translations->matching($criteria)->first();

        return $translation ?: null;
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
     * @return Collection<int, ProductAttributeValueInterface>
     */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(ProductAttributeValueInterface $attribute): self
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes->add($attribute);
        }

        return $this;
    }

    public function removeAttribute(ProductAttributeValueInterface $attribute): self
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
