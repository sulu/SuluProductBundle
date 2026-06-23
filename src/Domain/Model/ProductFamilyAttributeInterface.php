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

interface ProductFamilyAttributeInterface
{
    public function getId(): int;

    public function isRequired(): bool;

    public function setRequired(bool $required): self;

    public function getFamily(): ProductFamilyInterface;

    public function getAttribute(): AttributeInterface;

    public function setAttribute(AttributeInterface $attribute): self;
}
