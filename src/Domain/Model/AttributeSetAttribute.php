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

class AttributeSetAttribute implements AttributeSetAttributeInterface
{
    protected int $id;

    protected bool $required = false;

    protected int $position = 0;

    protected AttributeSetInterface $attributeSet;

    protected AttributeInterface $attribute;

    public function __construct(AttributeSetInterface $attributeSet, AttributeInterface $attribute)
    {
        $this->attributeSet = $attributeSet;
        $this->attribute = $attribute;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getAttributeSet(): AttributeSetInterface
    {
        return $this->attributeSet;
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
