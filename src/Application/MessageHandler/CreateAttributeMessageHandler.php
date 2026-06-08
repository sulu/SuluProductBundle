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

use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class CreateAttributeMessageHandler
{
    public function __construct(private AttributeRepositoryInterface $attributeRepository)
    {
    }

    public function __invoke(CreateAttributeMessage $message): AttributeInterface
    {
        /** @var array{
         *     locale: string,
         *     key: string,
         *     name: string,
         *     type: string,
         *     description: string|null,
         *     options: list<array{
         *        type: string,
         *        key: string,
         *        name: string,
         *     }>|null,
         * } $data */
        $data = $message->getData();
        $locale = $data['locale'];

        $attribute = $this->attributeRepository->create();
        $attribute->setKey($data['key']);
        $attribute->setType($data['type']);

        $translation = new AttributeTranslation($attribute, $locale, $data['name']);
        $translation->setDescription($data['description'] ?? null);
        $attribute->addTranslation($translation);

        $position = 0;
        foreach (($data['options'] ?? []) as $optionData) {
            $optionKey = $optionData['key'];

            if ('' === $optionKey) {
                continue;
            }

            $option = new AttributeOption($attribute, $optionKey);
            $option->setPosition($position++);
            $option->addTranslation(new AttributeOptionTranslation($option, $locale, $optionData['name']));
            $attribute->addOption($option);
        }

        $this->attributeRepository->save($attribute);

        return $attribute;
    }
}
