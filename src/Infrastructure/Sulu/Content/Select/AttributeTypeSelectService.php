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

namespace Sulu\Product\Infrastructure\Sulu\Content\Select;

use Sulu\Product\Application\AttributeType\AttributeTypeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AttributeTypeSelectService
{
    /**
     * @param iterable<AttributeTypeInterface> $attributeTypes
     */
    public function __construct(
        private iterable $attributeTypes,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<int, array{name: string, title: string}>
     */
    public function getValues(string $locale): array
    {
        $values = [];

        foreach ($this->attributeTypes as $type) {
            if (!$type->isAvailableInAdmin()) {
                continue;
            }

            $key = $type->getKey();
            $values[] = [
                'name' => $key,
                'title' => $this->translator->trans('sulu_product.type_' . $key, [], 'admin', $locale),
            ];
        }

        return $values;
    }
}
