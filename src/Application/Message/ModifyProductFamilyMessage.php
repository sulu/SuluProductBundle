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

namespace Sulu\Product\Application\Message;

/**
 * @phpstan-import-type ProductFamilyAttributeMessageData from CreateProductFamilyMessage
 *
 * @phpstan-type ModifyProductFamilyMessageIdentifier array{
 *     uuid: string,
 * }
 * @phpstan-type ModifyProductFamilyMessageData array{
 *     locale: string,
 *     name: string,
 *     description?: string|null,
 *     familyAttributes?: list<ProductFamilyAttributeMessageData>|null,
 * }
 */
class ModifyProductFamilyMessage
{
    /**
     * @param ModifyProductFamilyMessageIdentifier $identifier
     * @param ModifyProductFamilyMessageData $data
     */
    public function __construct(
        private readonly array $identifier,
        private readonly array $data,
    ) {
    }

    /**
     * @return ModifyProductFamilyMessageIdentifier
     */
    public function getIdentifier(): array
    {
        return $this->identifier;
    }

    public function getUuid(): string
    {
        return $this->identifier['uuid'];
    }

    public function getLocale(): string
    {
        return $this->data['locale'];
    }

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getDescription(): ?string
    {
        return $this->data['description'] ?? null;
    }

    /**
     * @return list<ProductFamilyAttributeMessageData>
     */
    public function getFamilyAttributes(): array
    {
        return $this->data['familyAttributes'] ?? [];
    }

    /**
     * @return ModifyProductFamilyMessageData
     */
    public function getData(): array
    {
        return $this->data;
    }
}
