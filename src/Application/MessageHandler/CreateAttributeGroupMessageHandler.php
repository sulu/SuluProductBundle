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

use Sulu\Product\Application\Message\CreateAttributeGroupMessage;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class CreateAttributeGroupMessageHandler
{
    public function __construct(
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
        private AttributeRepositoryInterface $attributeRepository,
    ) {
    }

    public function __invoke(CreateAttributeGroupMessage $message): AttributeGroupInterface
    {
        $attributeGroup = $this->attributeGroupRepository->create();

        $translation = new AttributeGroupTranslation($attributeGroup, $message->getLocale(), $message->getName());

        if (null !== $message->getDescription()) {
            $translation->setDescription($message->getDescription());
        }

        $attributeGroup->addTranslation($translation);

        foreach ($message->getAttributes() as $index => $entry) {
            $attribute = $this->attributeRepository->findOneBy(['uuid' => $entry['attribute']]);

            if (null === $attribute) {
                continue;
            }

            $groupAttr = new AttributeGroupAttribute($attributeGroup, $attribute);
            $groupAttr->setPosition($index);
            $attributeGroup->addGroupAttribute($groupAttr);
        }

        $this->attributeGroupRepository->save($attributeGroup);

        return $attributeGroup;
    }
}
