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

use Sulu\Product\Application\Message\ModifyAttributeSetMessage;
use Sulu\Product\Domain\Exception\AttributeSetNotFoundException;
use Sulu\Product\Domain\Model\AttributeSetAttribute;
use Sulu\Product\Domain\Model\AttributeSetInterface;
use Sulu\Product\Domain\Model\AttributeSetTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeSetRepositoryInterface;

final class ModifyAttributeSetMessageHandler
{
    public function __construct(
        private AttributeSetRepositoryInterface $attributeSetRepository,
        private AttributeRepositoryInterface $attributeRepository,
    ) {
    }

    public function __invoke(ModifyAttributeSetMessage $message): AttributeSetInterface
    {
        $attributeSet = $this->attributeSetRepository->findOneBy(['uuid' => $message->getUuid()]);

        if (null === $attributeSet) {
            throw new AttributeSetNotFoundException(['uuid' => $message->getUuid()]);
        }

        $translation = $attributeSet->getTranslation($message->getLocale());
        if (null === $translation) {
            $translation = new AttributeSetTranslation($attributeSet, $message->getLocale(), $message->getName());
            $attributeSet->addTranslation($translation);
        } else {
            $translation->setName($message->getName());
        }
        $translation->setDescription($message->getDescription());

        // Build map of existing setAttributes keyed by attribute UUID
        $existingMap = [];
        foreach ($attributeSet->getSetAttributes() as $setAttr) {
            $existingMap[$setAttr->getAttribute()->getUuid()] = $setAttr;
        }

        // Build set of submitted attribute UUIDs
        $submittedUuids = \array_map(
            fn (array $entry) => $entry['attribute'],
            $message->getAttributes(),
        );

        // Remove entries no longer in submitted list
        foreach ($existingMap as $uuid => $setAttr) {
            if (!\in_array($uuid, $submittedUuids, true)) {
                $attributeSet->removeSetAttribute($setAttr);
            }
        }

        // Add or update submitted entries
        foreach ($message->getAttributes() as $index => $entry) {
            $attrUuid = $entry['attribute'];

            if (isset($existingMap[$attrUuid])) {
                $setAttr = $existingMap[$attrUuid];
                $setAttr->setRequired($entry['required'] ?? false);
                $setAttr->setPosition($index);
            } else {
                $attribute = $this->attributeRepository->findOneBy(['uuid' => $attrUuid]);

                if (null === $attribute) {
                    continue;
                }

                $setAttr = new AttributeSetAttribute($attributeSet, $attribute);
                $setAttr->setRequired($entry['required'] ?? false);
                $setAttr->setPosition($index);
                $attributeSet->addSetAttribute($setAttr);
            }
        }

        $this->attributeSetRepository->save($attributeSet);

        return $attributeSet;
    }
}
