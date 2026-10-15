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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Product\Application\Attribute\AttributeDisplayFormatter;
use Sulu\Product\Domain\Model\AttributeInterface;

final class TextAttributeType implements AttributeTypeInterface, FormattableAttributeTypeInterface
{
    public function __construct(
        private readonly AttributeDisplayFormatter $displayFormatter,
    ) {
    }

    public function getKey(): string
    {
        return AttributeInterface::TYPE_TEXT;
    }

    public function configureForm(FormMetadata $form, AttributeInterface $attribute, string $locale): void
    {
    }

    public function formatValue(AttributeInterface $attribute, mixed $value, string $locale): ?string
    {
        return $this->displayFormatter->format($attribute, $value);
    }

    public function hasDisplayFormat(): bool
    {
        return true;
    }
}
