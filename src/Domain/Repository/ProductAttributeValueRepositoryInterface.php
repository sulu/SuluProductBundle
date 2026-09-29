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

namespace Sulu\Product\Domain\Repository;

use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;

/**
 * Implementation can be found in the following class:.
 *
 * @see \Sulu\Product\Infrastructure\Doctrine\Repository\ProductAttributeValueRepository
 */
interface ProductAttributeValueRepositoryInterface
{
    /**
     * @param array{
     *     attribute?: AttributeInterface,
     *     locale?: string,
     *     stage?: string,
     * } $filters
     *
     * Note: "locale" matches a localized value's dimension content exactly, but also always
     * matches an unlocalized value's dimension content (locale IS NULL) regardless of its own
     * value — an attribute value's dimension content is the unlocalized one for a
     * non-localized attribute and the localized one for a localized attribute (see
     * {@see \Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductAttributesDataMapper}),
     * so this single condition covers both without the caller special-casing
     * {@see AttributeInterface::isLocalized()}
     *
     * @return list<ProductAttributeValueInterface>
     */
    public function findBy(array $filters = []): array;
}
