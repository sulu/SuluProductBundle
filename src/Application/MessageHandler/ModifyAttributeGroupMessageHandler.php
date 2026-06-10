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
        $attributeGroup = $this->attributeGroupRepository->findOneBy(['uuid' => $message->getUuid()]);

        if (null === $attributeGroup) {
            throw new AttributeGroupNotFoundException(['uuid' => $message->getUuid()]);
        }

        $translation = $attributeGroup->getTranslation($message->getLocale());
        if (null === $translation) {
            $translation = new AttributeGroupTranslation($attributeGroup, $message->getLocale(), $message->getName());
            $attributeGroup->addTranslation($translation);
        } else {
            $translation->setName($message->getName());
        }
        $translation->setDescription($message->getDescription());

        // Build map of existing groupAttributes keyed by attribute UUID
        $existingMap = [];
        foreach ($attributeGroup->getGroupAttributes() as $groupAttr) {
            $existingMap[$groupAttr->getAttribute()->getUuid()] = $groupAttr;
        }

        // Build set of submitted attribute UUIDs
        $submittedUuids = \array_map(
            fn (array $entry) => $entry['attribute'],
            $message->getAttributes(),
        );

        // Remove entries no longer in submitted list
        foreach ($existingMap as $uuid => $groupAttr) {
            if (!\in_array($uuid, $submittedUuids, true)) {
                $attributeGroup->removeGroupAttribute($groupAttr);
            }
        }

        // Add or update submitted entries
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

                $groupAttr = new AttributeGroupAttribute($attributeGroup, $attribute);
                $groupAttr->setPosition($index);
                $attributeGroup->addGroupAttribute($groupAttr);
            }
        }

        $this->attributeGroupRepository->save($attributeGroup);

        return $attributeGroup;
    }
}
