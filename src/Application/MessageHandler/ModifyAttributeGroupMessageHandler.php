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

use Sulu\Product\Application\Message\ModifyAttributeGroupMessage;
use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class ModifyAttributeGroupMessageHandler
{
    public function __construct(
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
        private AttributeRepositoryInterface $attributeRepository,
    ) {
    }

    public function __invoke(ModifyAttributeGroupMessage $message): AttributeGroupInterface
    {
        $group = $this->attributeGroupRepository->findOneBy(['uuid' => $message->getUuid()]);

        if (null === $group) {
            throw new AttributeGroupNotFoundException(['uuid' => $message->getUuid()]);
        }

        $translation = $group->getTranslation($message->getLocale());
        if (null === $translation) {
            $translation = new AttributeGroupTranslation($group, $message->getLocale(), $message->getName());
            $group->addTranslation($translation);
        } else {
            $translation->setName($message->getName());
        }
        $translation->setDescription($message->getDescription());

        $existingMap = [];
        foreach ($group->getGroupAttributes() as $groupAttr) {
            $existingMap[$groupAttr->getAttribute()->getUuid()] = $groupAttr;
        }

        $submittedUuids = \array_map(
            fn (array $entry) => $entry['attribute'],
            $message->getAttributes(),
        );

        foreach ($existingMap as $uuid => $groupAttr) {
            if (!\in_array($uuid, $submittedUuids, true)) {
                $group->removeGroupAttribute($groupAttr);
            }
        }

        foreach ($message->getAttributes() as $index => $entry) {
            $attrUuid = $entry['attribute'];

            if (isset($existingMap[$attrUuid])) {
                $groupAttr = $existingMap[$attrUuid];
                $groupAttr->setPosition($index);
            } else {
                $attribute = $this->attributeRepository->findOneBy(['uuid' => $attrUuid]);

                if (null === $attribute) {
                    continue;
                }

                $groupAttr = new AttributeGroupAttribute($group, $attribute);
                $groupAttr->setPosition($index);
                $group->addGroupAttribute($groupAttr);
            }
        }

        $this->attributeGroupRepository->save($group);

        return $group;
    }
}
