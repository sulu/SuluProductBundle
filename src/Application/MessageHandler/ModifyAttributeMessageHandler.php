<?php

declare(strict_types=1);

namespace Sulu\Product\Application\MessageHandler;

use Sulu\Product\Application\Message\ModifyAttributeMessage;
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
        $identifier = $message->getIdentifier();
        $data = $message->getData();
        /** @var string $locale */
        $locale = $data['locale'];

        $attribute = $this->attributeRepository->findOneBy(['uuid' => $identifier['uuid'] ?? null]);

        if (null === $attribute) {
            throw new \RuntimeException(\sprintf('Attribute with uuid "%s" not found.', $identifier['uuid'] ?? ''));
        }

        if (isset($data['key'])) {
            $attribute->setKey((string) $data['key']);
        }

        if (isset($data['type'])) {
            $attribute->setType((string) $data['type']);
        }

        $translation = $attribute->getTranslation($locale);
        if (null !== $translation) {
            $translation->setName((string) ($data['name'] ?? ''));
            $translation->setDescription($data['description'] ?? null);
        } else {
            $newTranslation = new AttributeTranslation($attribute, $locale, (string) ($data['name'] ?? ''));
            $newTranslation->setDescription($data['description'] ?? null);
            $attribute->addTranslation($newTranslation);
        }

        $submittedOptions = $data['options'] ?? null;
        if (\is_array($submittedOptions)) {
            $submittedKeys = \array_filter(\array_column($submittedOptions, 'key'), fn($k) => '' !== (string) $k);

            foreach ($attribute->getOptions() as $option) {
                if (!\in_array($option->getKey(), $submittedKeys, true)) {
                    $attribute->removeOption($option);
                }
            }

            $position = 0;
            foreach ($submittedOptions as $optionData) {
                $optionKey = (string) ($optionData['key'] ?? '');

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

                if (null !== $optionTranslation) {
                    $optionTranslation->setName((string) ($optionData['name'] ?? ''));
                } else {
                    $option->addTranslation(new AttributeOptionTranslation($option, $locale, (string) ($optionData['name'] ?? '')));
                }
            }
        }

        $this->attributeRepository->save($attribute);

        return $attribute;
    }
}
