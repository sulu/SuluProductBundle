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
    public const ATTRIBUTES_FORM_KEY = 'product_attributes';
    public const LIST_KEY = 'products';

    /**
     * @internal
     */
    public function getId(): string;

    public function getUuid(): string;
}
