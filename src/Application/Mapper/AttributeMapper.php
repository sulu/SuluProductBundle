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

use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Application\Message\ModifyAttributeMessage;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class AttributeMapper implements AttributeMapperInterface
{
    public function __construct(private AttributeRepositoryInterface $attributeRepository)
    {
    }

    public function mapAttributeData(AttributeInterface $attribute, CreateAttributeMessage|ModifyAttributeMessage $message): void
    {
        $attribute->setKey($message->getKey());
        $attribute->setConfig($message->getConfig());

        $data = $message->getData();
        if (\array_key_exists('localized', $data)) {
            $attribute->setLocalized((bool) $data['localized']);
        }

        if (null === $attribute->getDefaultLocale()) {
            $attribute->setDefaultLocale($message->getLocale());
        }

        $this->mapTranslation($attribute, $message);
        $this->mapOptions($attribute, $message);

        // Todo: Move to Doctrine listener.
        if ($message instanceof CreateAttributeMessage) {
            $attribute->setType($message->getType());
            $this->mapCreatePosition($attribute, $message);
        } else {
            $this->syncPosition($attribute, $message->getPosition());
        }
    }

    private function mapCreatePosition(AttributeInterface $attribute, CreateAttributeMessage $message): void
    {
        $attributeGroup = $attribute->getGroup();
        $position = $message->getPosition();

        if (null !== $position) {
            foreach ($this->attributeRepository->findByGroupWithPositionAtLeast($attributeGroup, $position) as $other) {
                $other->setPosition($other->getPosition() + 1);
            }
            $attribute->setPosition($position);
        } else {
            $attribute->setPosition($this->attributeRepository->findNextPositionInGroup($attributeGroup));
        }

        $groupAttr = new AttributeGroupAttribute($attributeGroup, $attribute);
        $groupAttr->setPosition($attribute->getPosition());
        $attributeGroup->addGroupAttribute($groupAttr);
    }

    private function syncPosition(AttributeInterface $attribute, ?int $newPosition): void
    {
        $group = $attribute->getGroup();
        $oldPosition = $attribute->getPosition();

        if (null === $newPosition) {
            $newPosition = $this->attributeRepository->findNextPositionInGroup($group) - 1;
        }

        if ($newPosition === $oldPosition) {
            return;
        }

        if ($newPosition > $oldPosition) {
            foreach ($this->attributeRepository->findByGroupWithPositionBetween($group, $oldPosition + 1, $newPosition, $attribute) as $other) {
                $other->setPosition($other->getPosition() - 1);
            }
        } else {
            foreach ($this->attributeRepository->findByGroupWithPositionAtLeast($group, $newPosition, $attribute) as $other) {
                $other->setPosition($other->getPosition() + 1);
            }
        }

        $attribute->setPosition($newPosition);
    }

    private function mapTranslation(AttributeInterface $attribute, CreateAttributeMessage|ModifyAttributeMessage $message): void
    {
        $locale = $message->getLocale();
        $translation = $attribute->getTranslation($locale);

        if (null === $translation) {
            $translation = new AttributeTranslation($attribute, $locale, $message->getName());
            $attribute->addTranslation($translation);
        } else {
            $translation->setName($message->getName());
        }

        if ($message->getDescription()) {
            $translation->setDescription($message->getDescription());
        }
    }

    private function mapOptions(AttributeInterface $attribute, CreateAttributeMessage|ModifyAttributeMessage $message): void
    {
        $submittedOptions = $message->getOptions();
        if (null === $submittedOptions) {
            return;
        }

        $submittedKeys = \array_map(static fn (array $option): string => $option['key'], $submittedOptions);

        foreach ($attribute->getOptions() as $option) {
            if (!\in_array($option->getKey(), $submittedKeys, true)) {
                $attribute->removeOption($option);
            }
        }

        $position = 0;
        foreach ($submittedOptions as $optionData) {
            $optionKey = $optionData['key'];
            $option = $attribute->getOption($optionKey);

            if (null === $option) {
                $option = new AttributeOption($attribute, $optionKey);
                $attribute->addOption($option);
            }

            $option->setPosition($position++);

            $optionTranslation = $option->getTranslation($message->getLocale());
            $optionName = $optionData['name'];

            if (null !== $optionTranslation) {
                $optionTranslation->setName($optionName);
            } else {
                $option->addTranslation(new AttributeOptionTranslation($option, $message->getLocale(), $optionName));
            }
        }
    }
}
