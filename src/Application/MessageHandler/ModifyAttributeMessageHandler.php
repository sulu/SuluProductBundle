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

use Sulu\Product\Application\Message\ModifyAttributeMessage;
use Sulu\Product\Domain\Exception\AttributeNotFoundException;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class ModifyAttributeMessageHandler
{
    public function __construct(private AttributeRepositoryInterface $attributeRepository)
    {
    }

    public function __invoke(ModifyAttributeMessage $message): AttributeInterface
    {
        /** @var array{
         *     locale: string,
         *     key?: string,
         *     type?: string,
         *     name?: string,
         *     description?: string|null,
         *     options?: list<array{
         *         type: string,
         *         key: string,
         *         name: string,
         *     }>|null,
         * } $data */
        $data = $message->getData();
        $identifier = $message->getIdentifier();
        $locale = $data['locale'];

        $attribute = $this->attributeRepository->findOneBy(['uuid' => $identifier['uuid'] ?? null]);

        if (null === $attribute) {
            throw new AttributeNotFoundException(['uuid' => $identifier['uuid'] ?? null]);
        }

        if (isset($data['key'])) {
            $attribute->setKey($data['key']);
        }

        if (isset($data['type'])) {
            $attribute->setType($data['type']);
        }

        $name = $data['name'] ?? '';
        $description = $data['description'] ?? null;

        $translation = $attribute->getTranslation($locale);
        if (null !== $translation) {
            $translation->setName($name);
            $translation->setDescription($description);
        } else {
            $newTranslation = new AttributeTranslation($attribute, $locale, $name);
            $newTranslation->setDescription($description);
            $attribute->addTranslation($newTranslation);
        }

        $submittedOptions = $data['options'] ?? null;
        if (null !== $submittedOptions) {
            $submittedKeys = \array_filter(
                \array_map(fn (array $o) => $o['key'], $submittedOptions),
                fn (string $k) => '' !== $k,
            );

            foreach ($attribute->getOptions() as $option) {
                if (!\in_array($option->getKey(), $submittedKeys, true)) {
                    $attribute->removeOption($option);
                }
            }

            $position = 0;
            foreach ($submittedOptions as $optionData) {
                $optionKey = $optionData['key'];

                if ('' === $optionKey) {
                    continue;
                }

                $option = $attribute->getOption($optionKey);

                if (null === $option) {
                    $option = new AttributeOption($attribute, $optionKey);
                    $attribute->addOption($option);
                }

                $option->setPosition($position++);

                $optionTranslation = $option->getTranslation($locale);
                $optionName = $optionData['name'];

                if (null !== $optionTranslation) {
                    $optionTranslation->setName($optionName);
                } else {
                    $option->addTranslation(new AttributeOptionTranslation($option, $locale, $optionName));
                }
            }
        }

        $this->attributeRepository->save($attribute);

        return $attribute;
    }
}
