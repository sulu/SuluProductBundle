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

namespace Sulu\Product\Application\MessageHandler;

use Sulu\Product\Application\Message\ModifyProductFamilyMessage;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;

final class ModifyProductFamilyMessageHandler
{
    public function __construct(
        private ProductFamilyRepositoryInterface $productFamilyRepository,
        private AttributeRepositoryInterface $attributeRepository,
    ) {
    }

    public function __invoke(ModifyProductFamilyMessage $message): ProductFamilyInterface
    {
        $family = $this->productFamilyRepository->getOneBy(['uuid' => $message->getUuid()]);

        $translation = $family->getTranslation($message->getLocale());
        if (null === $translation) {
            $translation = new ProductFamilyTranslation($family, $message->getLocale(), $message->getName());
            $family->addTranslation($translation);
        } else {
            $translation->setName($message->getName());
        }
        $translation->setDescription($message->getDescription());

        $existingMap = [];
        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            $existingMap[$familyAttribute->getAttribute()->getId()] = $familyAttribute;
        }

        $submittedIds = \array_map(
            static fn (array $entry) => $entry['attribute'],
            $message->getFamilyAttributes(),
        );

        foreach ($existingMap as $id => $familyAttribute) {
            if (!\in_array($id, $submittedIds, true)) {
                $family->removeFamilyAttribute($familyAttribute);
            }
        }

        foreach ($message->getFamilyAttributes() as $entry) {
            $attributeId = $entry['attribute'];

            if (isset($existingMap[$attributeId])) {
                $existingMap[$attributeId]->setRequired($entry['required']);

                continue;
            }

            $attribute = $this->attributeRepository->findOneBy(['id' => $attributeId]);
            if (null === $attribute) {
                continue;
            }

            $familyAttribute = new ProductFamilyAttribute($family, $attribute);
            $familyAttribute->setRequired($entry['required']);
            $family->addFamilyAttribute($familyAttribute);
        }

        $this->productFamilyRepository->save($family);

        return $family;
    }
}
