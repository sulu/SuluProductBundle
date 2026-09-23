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
use Sulu\Product\Domain\Exception\AttributeOptionKeyNotUniqueException;
use Sulu\Product\Domain\Exception\InvalidDateDisplayFormatException;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class AttributeMapper implements AttributeMapperInterface
{
    /** Free text has no discrete values to filter by. */
    private const FILTERABLE_TYPES = [
        AttributeInterface::TYPE_NUMBER,
        AttributeInterface::TYPE_DATE,
        AttributeInterface::TYPE_OPTIONS,
    ];

    public function __construct(private AttributeRepositoryInterface $attributeRepository)
    {
    }

    public function mapAttributeData(AttributeInterface $attribute, CreateAttributeMessage|ModifyAttributeMessage $message): void
    {
        $type = $message instanceof CreateAttributeMessage ? $message->getType() : $attribute->getType();
        if (AttributeInterface::TYPE_DATE === $type) {
            $this->assertDateDisplayFormatRendersADate($message->getConfig()['displayFormat'] ?? null);
        }

        $attribute->setKey($message->getKey());
        $attribute->setConfig($message->getConfig());

        $data = $message->getData();
        if (\array_key_exists('localized', $data)) {
            $attribute->setLocalized((bool) $data['localized']);
        }

        if (\array_key_exists('filterable', $data)) {
            $attribute->setFilterable((bool) $data['filterable'] && \in_array($type, self::FILTERABLE_TYPES, true));
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

    /** ICU renders unquoted words as an empty string, which hides the value on the website without an error. */
    private function assertDateDisplayFormatRendersADate(mixed $displayFormat): void
    {
        if (!\is_string($displayFormat) || '' === $displayFormat) {
            return;
        }

        $formatter = new \IntlDateFormatter('en', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, 'UTC', null, $displayFormat);

        if (!$formatter->format(0)) {
            throw new InvalidDateDisplayFormatException($displayFormat);
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

        $submittedKeys = [];
        foreach ($submittedOptions as $optionData) {
            if (isset($submittedKeys[$optionData['key']])) {
                throw new AttributeOptionKeyNotUniqueException($optionData['key']);
            }

            $submittedKeys[$optionData['key']] = true;
        }

        $existingOptions = [];
        foreach ($attribute->getOptions() as $option) {
            $existingOptions[$option->getId()] = $option;
        }

        // An option keeps its id when its key changes; a duplicated block carries its source's id, so only the first claims it.
        $matchedOptions = [];
        foreach ($submittedOptions as $index => $optionData) {
            $optionId = $optionData['id'] ?? null;
            if (null !== $optionId && isset($existingOptions[$optionId])) {
                $matchedOptions[$index] = $existingOptions[$optionId];
                unset($existingOptions[$optionId]);
            }
        }

        foreach ($existingOptions as $option) {
            $attribute->removeOption($option);
        }

        $position = 0;
        foreach ($submittedOptions as $index => $optionData) {
            $optionKey = $optionData['key'];
            $option = $matchedOptions[$index] ?? null;

            if (null === $option) {
                $option = new AttributeOption($attribute, $optionKey);
                $attribute->addOption($option);
            }

            $option->setKey($optionKey);
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
