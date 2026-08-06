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

use Sulu\Component\Persistence\Model\AuditableInterface;
use Sulu\Content\Domain\Model\ContentRichEntityInterface;

/**
 * @extends ContentRichEntityInterface<ProductDimensionContentInterface>
 */
interface ProductInterface extends AuditableInterface, ContentRichEntityInterface
{
    public const TEMPLATE_TYPE = 'product';
    public const RESOURCE_KEY = 'products';
    public const FORM_KEY = 'product_details';
    public const LIST_KEY = 'products';

    public const LIST_KEY_VARIANTS = 'product_variants';
    public const FORM_KEY_VARIANT = 'product_variant';

    public const LIST_KEY_VERSIONS = 'products_versions';

    public const TYPE_PRODUCT = 'product';
    public const TYPE_PRODUCT_WITH_VARIANTS = 'product_with_variants';
    public const TYPE_VARIANT = 'variant';

    /**
     * @internal
     */
    public function getId(): string;

    public function getUuid(): string;

    public function getType(): string;

    public function setType(string $type): self;

    public function isType(string $type): bool;

    public function getParent(): ?self;

    public function setParent(?self $parent): self;
}
