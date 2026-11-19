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

namespace Sulu\Product\Infrastructure\Sulu\Content\DataMapper;

use Sulu\Content\Application\ContentDataMapper\DataMapper\DataMapperInterface;
use Sulu\Content\Domain\Factory\CategoryFactoryInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Webmozart\Assert\Assert;

/**
 * Must run after the taxonomy mapper: it replaces the excerpt categories, which would
 * bring back a previous main category that is removed here.
 */
class ProductMainCategoryDataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly CategoryFactoryInterface $categoryFactory,
    ) {
    }

    public function map(
        DimensionContentInterface $unlocalizedDimensionContent,
        DimensionContentInterface $localizedDimensionContent,
        array $data,
    ): void {
        if (!$localizedDimensionContent instanceof ProductDimensionContentInterface
            || !\array_key_exists('mainCategory', $data)
        ) {
            return;
        }

        $mainCategoryId = $data['mainCategory'];
        Assert::nullOrInteger($mainCategoryId);

        $mainCategory = null === $mainCategoryId
            ? null
            : ($this->categoryFactory->create([$mainCategoryId])[0] ?? null);

        $localizedDimensionContent->setMainCategory($mainCategory);
    }
}
