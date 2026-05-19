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

use Symfony\Contracts\Translation\TranslatorInterface;

final class AttributeTypeSelectService
{
    /**
     * @param array<string, array{form_key: string|null, form_type: string|null}> $attributeTypes
     */
    public function __construct(
        private array $attributeTypes,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<int, array{name: string, title: string}>
     */
    public function getValues(string $locale): array
    {
        return \array_values(\array_map(
            fn(string $key) => [
                'name'  => $key,
                'title' => $this->translator->trans('sulu_product.type_' . $key, [], 'admin', $locale),
            ],
            \array_keys($this->attributeTypes),
        ));
    }
}
