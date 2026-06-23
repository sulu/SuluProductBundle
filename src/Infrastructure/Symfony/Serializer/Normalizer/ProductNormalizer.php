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

use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class ProductNormalizer implements NormalizerInterface
{
    public function __construct(
        private AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    /**
     * @param ProductInterface $data
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $locale = \is_string($context['locale'] ?? null) ? $context['locale'] : '';
        $translation = $data->getTranslation($locale);

        $attributes = [];

        foreach ($data->getProductFamily()->getFamilyAttributes() as $familyAttribute) {
            $attribute = $familyAttribute->getAttribute();
            if (!$this->attributeTypeRegistry->has($attribute->getType())) {
                continue;
            }

            $attributes[$attribute->getId()] = null;
        }

        foreach ($data->getAttributes() as $value) {
            $attribute = $value->getAttribute();
            if (!$this->attributeTypeRegistry->has($attribute->getType())) {
                continue;
            }

            $type = $this->attributeTypeRegistry->get($attribute->getType());
            $attributes[$attribute->getId()] = $type->readValue($value);
        }

        return [
            'id' => $data->getUuid(),
            'name' => $translation?->getName() ?? '',
            'code' => $data->getCode(),
            'externalIdentifier' => $data->getExternalIdentifier(),
            'productFamily' => $data->getProductFamily()->getUuid(),
            'attributes' => $attributes,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ProductInterface;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [ProductInterface::class => true];
    }
}
