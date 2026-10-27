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

use Sulu\Product\Domain\Value\ProductFamilyAttributeModeEnum;

interface ProductFamilyAttributeInterface
{
    public function getId(): int;

    public function isRequired(): bool;

    public function setRequired(bool $required): self;

    public function getMode(): ProductFamilyAttributeModeEnum;

    public function setMode(ProductFamilyAttributeModeEnum $mode): self;

    /** Whether a product of that type holds this attribute. */
    public function isAvailable(string $productType): bool;

    /** Whether a product of that type has to carry a value; an overridable one is required on the product only. */
    public function isRequiredFor(string $productType): bool;

    public function getFamily(): ProductFamilyInterface;

    public function getAttribute(): AttributeInterface;

    public function setAttribute(AttributeInterface $attribute): self;
}
