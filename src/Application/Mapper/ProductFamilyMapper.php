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

use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Product\Application\Message\CreateProductFamilyMessage;
use Sulu\Product\Application\Message\ModifyProductFamilyMessage;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class ProductFamilyMapper implements ProductFamilyMapperInterface
{
    public function __construct(
        private AttributeRepositoryInterface $attributeRepository,
        private MediaRepositoryInterface $mediaRepository,
    ) {
    }

    public function mapProductFamilyData(
        ProductFamilyInterface $family,
        CreateProductFamilyMessage|ModifyProductFamilyMessage $message,
    ): void {
        $this->mapTranslation($family, $message);
        $this->mapImage($family, $message);
        $this->mapAttributes($family, $message);
    }

    /** An unknown media id clears the image, like an absent one. */
    private function mapImage(
        ProductFamilyInterface $family,
        CreateProductFamilyMessage|ModifyProductFamilyMessage $message,
    ): void {
        $imageId = $message->getImageId();

        /** @var MediaInterface|null $image */
        $image = null === $imageId ? null : $this->mediaRepository->findMediaById($imageId);
        $family->setImage($image);
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
        $submitted = [];
        foreach ($message->getAttributes() as $entry) {
            $submitted[$entry['id']] = $entry;
        }

        $existingMap = [];
        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            $uuid = $familyAttribute->getAttribute()->getUuid();
            // A persisted attribute always has a uuid; skipping keeps an unexpected null from
            // reading as "not submitted" and silently removing the assignment.
            if (null === $uuid) {
                continue;
            }

            if (!isset($submitted[$uuid])) {
                $family->removeFamilyAttribute($familyAttribute);

                continue;
            }

            $existingMap[$uuid] = $familyAttribute;
        }

        foreach ($submitted as $uuid => $entry) {
            $familyAttribute = $existingMap[$uuid] ?? null;

            if (null === $familyAttribute) {
                $attribute = $this->attributeRepository->findOneBy(['uuid' => $uuid]);
                if (null === $attribute) {
                    continue;
                }

                $familyAttribute = new ProductFamilyAttribute($family, $attribute);
                $family->addFamilyAttribute($familyAttribute);
            }

            $familyAttribute->setRequired($entry['required']);
            $familyAttribute->setVariantSpecific($entry['variantSpecific']);
        }
    }
}
