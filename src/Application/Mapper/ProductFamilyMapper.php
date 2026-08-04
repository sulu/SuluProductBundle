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

use Sulu\Product\Application\Message\CreateProductFamilyMessage;
use Sulu\Product\Application\Message\ModifyProductFamilyMessage;
use Sulu\Product\Domain\Exception\InvalidVariantAttributeException;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class ProductFamilyMapper implements ProductFamilyMapperInterface
{
    public function __construct(
        private AttributeRepositoryInterface $attributeRepository,
    ) {
    }

    public function mapProductFamilyData(
        ProductFamilyInterface $family,
        CreateProductFamilyMessage|ModifyProductFamilyMessage $message,
    ): void {
        $this->mapTranslation($family, $message);
        $this->mapAttributes($family, $message);
    }

    private function mapTranslation(
        ProductFamilyInterface $family,
        CreateProductFamilyMessage|ModifyProductFamilyMessage $message,
    ): void {
        if (null === $family->getDefaultLocale()) {
            $family->setDefaultLocale($message->getLocale());
        }

        $translation = $family->getTranslation($message->getLocale());

        if (null === $translation) {
            $translation = new ProductFamilyTranslation($family, $message->getLocale(), $message->getName());
            $family->addTranslation($translation);
        } else {
            $translation->setName($message->getName());
        }

        $translation->setDescription($message->getDescription());
    }

    private function mapAttributes(
        ProductFamilyInterface $family,
        CreateProductFamilyMessage|ModifyProductFamilyMessage $message,
    ): void {
        $this->assertVariantAttributesEnabled($message->getAttributes());

        $enabledAttributes = \array_filter(
            $message->getAttributes(),
            static fn (array $entry): bool => $entry['enabled'],
        );

        $existingMap = [];
        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            $existingMap[$familyAttribute->getAttribute()->getId()] = $familyAttribute;
        }

        foreach ($existingMap as $attributeId => $familyAttribute) {
            if (!isset($enabledAttributes[$attributeId])) {
                $family->removeFamilyAttribute($familyAttribute);
            }
        }

        foreach ($enabledAttributes as $attributeId => $entry) {
            if (isset($existingMap[$attributeId])) {
                $existingMap[$attributeId]->setRequired($entry['required']);
                $existingMap[$attributeId]->setVariantSpecific($entry['variantSpecific']);

                continue;
            }

            $attribute = $this->attributeRepository->findOneBy(['id' => $attributeId]);
            if (null === $attribute) {
                continue;
            }

            $familyAttribute = new ProductFamilyAttribute($family, $attribute);
            $familyAttribute->setRequired($entry['required']);
            $familyAttribute->setVariantSpecific($entry['variantSpecific']);
            $family->addFamilyAttribute($familyAttribute);
        }
    }

    /**
     * @param array<int, array{enabled: bool, required: bool, variantSpecific: bool}> $attributes
     *
     * @throws InvalidVariantAttributeException
     */
    private function assertVariantAttributesEnabled(array $attributes): void
    {
        foreach ($attributes as $attributeId => $entry) {
            if ($entry['variantSpecific'] && !$entry['enabled']) {
                throw new InvalidVariantAttributeException($attributeId);
            }
        }
    }
}
