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

use Sulu\Product\Application\Message\CreateProductFamilyMessage;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;

final class CreateProductFamilyMessageHandler
{
    public function __construct(
        private ProductFamilyRepositoryInterface $productFamilyRepository,
        private AttributeRepositoryInterface $attributeRepository,
    ) {
    }

    public function __invoke(CreateProductFamilyMessage $message): ProductFamilyInterface
    {
        $family = $this->productFamilyRepository->create();

        $translation = new ProductFamilyTranslation($family, $message->getLocale(), $message->getName());
        if (null !== $message->getDescription()) {
            $translation->setDescription($message->getDescription());
        }
        $family->addTranslation($translation);

        foreach ($message->getFamilyAttributes() as $entry) {
            $attribute = $this->attributeRepository->findOneBy(['id' => $entry['attribute']]);
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
