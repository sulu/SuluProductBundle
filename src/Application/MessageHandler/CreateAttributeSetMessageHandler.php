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

use Sulu\Product\Application\Message\CreateAttributeSetMessage;
use Sulu\Product\Domain\Model\AttributeSetAttribute;
use Sulu\Product\Domain\Model\AttributeSetInterface;
use Sulu\Product\Domain\Model\AttributeSetTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeSetRepositoryInterface;

final class CreateAttributeSetMessageHandler
{
    public function __construct(
        private AttributeSetRepositoryInterface $attributeSetRepository,
        private AttributeRepositoryInterface $attributeRepository,
    ) {
    }

    public function __invoke(CreateAttributeSetMessage $message): AttributeSetInterface
    {
        $attributeSet = $this->attributeSetRepository->create();

        $translation = new AttributeSetTranslation($attributeSet, $message->getLocale(), $message->getName());

        if (null !== $message->getDescription()) {
            $translation->setDescription($message->getDescription());
        }

        $attributeSet->addTranslation($translation);

        foreach ($message->getAttributes() as $index => $entry) {
            $attribute = $this->attributeRepository->findOneBy(['uuid' => $entry['attribute']]);

            if (null === $attribute) {
                continue;
            }

            $setAttr = new AttributeSetAttribute($attributeSet, $attribute);
            $setAttr->setRequired($entry['required'] ?? false);
            $setAttr->setPosition($index);
            $attributeSet->addSetAttribute($setAttr);
        }

        $this->attributeSetRepository->save($attributeSet);

        return $attributeSet;
    }
}
