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

class ProductAssociation implements ProductAssociationInterface
{
    protected ?int $id = null;

    public function __construct(
        protected readonly ProductDimensionContentInterface $productDimensionContent,
        protected readonly ProductInterface $target,
        protected readonly string $type,
        protected int $position = 0,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductDimensionContent(): ProductDimensionContentInterface
    {
        return $this->productDimensionContent;
    }

    public function getTarget(): ProductInterface
    {
        return $this->target;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
