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

namespace Sulu\Product\Infrastructure\Symfony\Serializer\Normalizer;

use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class ProductFamilyNormalizer implements NormalizerInterface
{
    public function __construct(
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
    ) {
    }

    /**
     * @param ProductFamilyInterface $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $locale = \is_string($context['locale'] ?? null) ? $context['locale'] : '';
        $translation = $data->getTranslation($locale);
        if (null === $translation) {
            $defaultLocale = $data->getDefaultLocale();
            if (null !== $defaultLocale) {
                $translation = $data->getTranslation($defaultLocale);
            }
        }

        $attributes = [];
        foreach ($this->attributeGroupRepository->findAll() as $group) {
            foreach ($group->getGroupAttributes() as $groupAttribute) {
                $attributes[$groupAttribute->getAttribute()->getId()] = [
                    'enabled' => false,
                    'required' => false,
                    'variantSpecific' => false,
                ];
            }
        }

        foreach ($data->getFamilyAttributes() as $familyAttribute) {
            $attributes[$familyAttribute->getAttribute()->getId()] = [
                'enabled' => true,
                'required' => $familyAttribute->isRequired(),
                'variantSpecific' => $familyAttribute->isVariantSpecific(),
            ];
        }

        return [
            'id' => $data->getUuid() ?? '',
            'name' => $translation?->getName() ?? '',
            'description' => $translation?->getDescription(),
            'externalIdentifier' => $data->getExternalIdentifier(),
            'attributes' => $attributes,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ProductFamilyInterface;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [ProductFamilyInterface::class => true];
    }
}
