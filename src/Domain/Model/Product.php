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

    public function __construct(?string $uuid = null)
    {
        $this->uuid = $uuid ?: Uuid::v7()->toRfc4122();
        $this->initializeDimensionContents();
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
}
