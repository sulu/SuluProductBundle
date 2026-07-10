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
        $group = $this->attributeGroupRepository->create();
        $group->setDefaultLocale($message->getLocale());

        $translation = new AttributeGroupTranslation($group, $message->getLocale(), $message->getName());

        if (null !== $message->getDescription()) {
            $translation->setDescription($message->getDescription());
        }

        $group->addTranslation($translation);

        foreach ($message->getAttributes() as $index => $entry) {
            $attribute = $this->attributeRepository->findOneBy(['uuid' => $entry['attribute']]);

            if (null === $attribute) {
                continue;
            }

            $groupAttr = new AttributeGroupAttribute($group, $attribute);
            $groupAttr->setPosition($index);
            $group->addGroupAttribute($groupAttr);
        }

        $this->attributeGroupRepository->save($group);

        return $group;
    }
}
