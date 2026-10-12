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

class ProductFamilyAttribute implements ProductFamilyAttributeInterface
{
    protected int $id;

    protected bool $required = false;

    protected string $mode = self::MODE_SHARED;

    protected ProductFamilyInterface $family;

    protected AttributeInterface $attribute;

    public function __construct(ProductFamilyInterface $family, AttributeInterface $attribute)
    {
        $this->family = $family;
        $this->attribute = $attribute;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;

        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): self
    {
        if (!\in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'Unknown family attribute mode "%s", expected one of: %s.',
                $mode,
                \implode(', ', self::MODES),
            ));
        }

        $this->mode = $mode;

        return $this;
    }

    public function isAvailable(string $productType): bool
    {
        return match ($productType) {
            ProductInterface::TYPE_VARIANT => self::MODE_SHARED !== $this->mode,
            ProductInterface::TYPE_PRODUCT_WITH_VARIANTS => self::MODE_VARIANT_SPECIFIC !== $this->mode,
            default => true,
        };
    }

    public function isRequiredFor(string $productType): bool
    {
        if (!$this->required || !$this->isAvailable($productType)) {
            return false;
        }

        return self::MODE_OVERRIDABLE !== $this->mode || ProductInterface::TYPE_VARIANT !== $productType;
    }

    public function getFamily(): ProductFamilyInterface
    {
        return $this->family;
    }

    public function getAttribute(): AttributeInterface
    {
        return $this->attribute;
    }

    public function setAttribute(AttributeInterface $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }
}
