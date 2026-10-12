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
    /** One value on the product, shared by all variants. */
    public const MODE_SHARED = 'shared';

    /** One value per variant, none on the product. */
    public const MODE_VARIANT_SPECIFIC = 'variantSpecific';

    /** The product holds the default, a variant may override it. */
    public const MODE_OVERRIDABLE = 'overridable';

    public const MODES = [self::MODE_SHARED, self::MODE_VARIANT_SPECIFIC, self::MODE_OVERRIDABLE];

    public function getId(): int;

    public function isRequired(): bool;

    public function setRequired(bool $required): self;

    /** One of {@see self::MODES}. */
    public function getMode(): string;

    /**
     * @throws \InvalidArgumentException when the mode is not one of {@see self::MODES}
     */
    public function setMode(string $mode): self;

    /** Whether a product of that type holds this attribute. */
    public function isAvailable(string $productType): bool;

    /** Whether a product of that type has to carry a value; an overridable one is required on the product only. */
    public function isRequiredFor(string $productType): bool;

    public function getFamily(): ProductFamilyInterface;

    public function getAttribute(): AttributeInterface;

    public function setAttribute(AttributeInterface $attribute): self;
}
