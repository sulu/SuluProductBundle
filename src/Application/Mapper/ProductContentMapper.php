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

namespace Sulu\Product\Application\Mapper;

use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Webmozart\Assert\Assert;

/**
 * @internal This class should not be instantiated by a project.
 *           Create an own ProductMapper to extend the handler with custom logic.
 */
final class ProductContentMapper implements ProductMapperInterface
{
    public function __construct(
        private readonly ContentPersisterInterface $contentPersister,
    ) {
    }

    public function mapProductData(ProductInterface $product, array $data): void
    {
        $locale = $data['locale'] ?? null;
        Assert::string($locale);

        $this->contentPersister->persist($product, $data, [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ]);
    }
}
