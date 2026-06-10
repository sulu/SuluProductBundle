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

class AttributeGroupAttribute implements AttributeGroupAttributeInterface
{
    protected int $id;

    protected int $position = 0;

    protected AttributeGroupInterface $attributeGroup;

    protected AttributeInterface $attribute;

    public function __construct(AttributeGroupInterface $attributeGroup, AttributeInterface $attribute)
    {
        $this->attributeGroup = $attributeGroup;
        $this->attribute = $attribute;
    }

    public function getId(): int
    {
        return $this->id;
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

    public function getAttributeGroup(): AttributeGroupInterface
    {
        return $this->attributeGroup;
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
