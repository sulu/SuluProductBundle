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
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;

class ProductAttributesNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    /**
     * @return string[]
     */
    public function getIgnoredAttributes(object $object): array
    {
        if (!$object instanceof ProductDimensionContentInterface) {
            return [];
        }

        return ['attributes'];
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

        $attributesMap = [];

        $productFamily = $object->getProductFamily();
        if (null !== $productFamily) {
            foreach ($productFamily->getFamilyAttributes() as $familyAttribute) {
                $attributesMap[$familyAttribute->getAttribute()->getId()] = null;
            }
        }

        foreach ($object->getAttributes() as $attrValue) {
            $attribute = $attrValue->getAttribute();
            $type = $this->attributeTypeRegistry->get($attribute->getType());
            $attributesMap[$attribute->getId()] = $type->readValue($attrValue);
        }

        $normalizedData['attributes'] = $attributesMap;

        return $normalizedData;
    }
}
