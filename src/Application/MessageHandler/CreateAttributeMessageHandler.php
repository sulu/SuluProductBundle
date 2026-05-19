<?php

declare(strict_types=1);

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
        $data = $message->getData();
        $locale = (string) $data['locale'];

        $attribute = $this->attributeRepository->create();
        $attribute->setKey((string) $data['key']);
        $attribute->setType((string) ($data['type'] ?? AttributeInterface::TYPE_NUMBER));

        $translation = new AttributeTranslation($attribute, $locale, (string) ($data['name'] ?? ''));
        $translation->setDescription($data['description'] ?? null);
        $attribute->addTranslation($translation);

        $position = 0;
        foreach (($data['options'] ?? []) as $optionData) {
            $optionKey = (string) ($optionData['key'] ?? '');

            if ('' === $optionKey) {
                continue;
            }

            $option = new AttributeOption($attribute, $optionKey);
            $option->setPosition($position++);
            $option->addTranslation(new AttributeOptionTranslation($option, $locale, (string) ($optionData['name'] ?? '')));
            $attribute->addOption($option);
        }

        $this->attributeRepository->save($attribute);

        return $attribute;
    }
}
