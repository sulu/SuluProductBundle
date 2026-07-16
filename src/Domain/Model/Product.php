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

    protected string $type = ProductInterface::TYPE_SIMPLE;

    protected ?ProductInterface $parent = null;

    /** @var Collection<int, ProductInterface> */
    protected Collection $variants;

    public function __construct(?string $uuid = null)
    {
        $this->uuid = $uuid ?: Uuid::v7()->toRfc4122();
        $this->initializeDimensionContents();
        $this->variants = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->uuid;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return ProductDimensionContentInterface
     */
    public function createDimensionContent(): DimensionContentInterface
    {
        return new ProductDimensionContent($this);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function isVariantProduct(): bool
    {
        return ProductInterface::TYPE_VARIANT === $this->type;
    }

    public function isVariant(): bool
    {
        return null !== $this->parent;
    }

    public function getParent(): ?ProductInterface
    {
        return $this->parent;
    }

    public function setParent(?ProductInterface $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, ProductInterface>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }
}
