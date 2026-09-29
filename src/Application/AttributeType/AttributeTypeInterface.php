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

namespace Sulu\Product\Application\AttributeType;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;

interface AttributeTypeInterface
{
    public function getKey(): string;

    public function getFormKey(): string;

    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void;

    /**
     * Names the rows a value is stored in, e.g. `['value']`, `['from', 'to']` or one per selected
     * option. The data mapper creates the missing rows and removes the ones not named.
     *
     * @return list<string>
     */
    public function getValueKeys(AttributeInterface $attribute, mixed $raw): array;

    /**
     * The value as the admin API and the website see it.
     *
     * @param array<string, ProductAttributeValueInterface> $rows the stored rows by value key, possibly none
     */
    public function readValue(array $rows): mixed;

    /**
     * Validates the whole value before changing any row.
     *
     * @param array<string, ProductAttributeValueInterface> $rows exactly the rows named by {@see getValueKeys()}
     */
    public function writeValue(array $rows, mixed $raw): void;
}
