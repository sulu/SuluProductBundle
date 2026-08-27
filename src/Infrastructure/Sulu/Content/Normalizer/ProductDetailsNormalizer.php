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

namespace Sulu\Product\Infrastructure\Sulu\Content\Normalizer;

use Sulu\Content\Application\ContentNormalizer\Normalizer\NormalizerInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;

class ProductDetailsNormalizer implements NormalizerInterface
{
    /**
     * @return string[]
     */
    public function getIgnoredAttributes(object $object): array
    {
        if (!$object instanceof ProductDimensionContentInterface) {
            return [];
        }

        return ['productFamily'];
    }

    /**
     * @param array<string, mixed> $normalizedData
     *
     * @return array<string, mixed>
     */
    public function enhance(object $object, array $normalizedData): array
    {
        if (!$object instanceof ProductDimensionContentInterface) {
            return $normalizedData;
        }

        $normalizedData['title'] = $object->getTitle();
        $normalizedData['code'] = $object->getCode();
        $normalizedData['externalIdentifier'] = $object->getExternalIdentifier();
        $normalizedData['productFamily'] = $object->getProductFamily()?->getUuid();
        $normalizedData['status'] = $object->getStatus();
        $normalizedData['details'] = $object->getDetailsData();
        $normalizedData['type'] = $object->getResource()->getType();
        $normalizedData['position'] = $object->getResource()->getPosition();

        return $normalizedData;
    }
}
