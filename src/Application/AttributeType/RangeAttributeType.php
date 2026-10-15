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

/**
 * The attribute's min, max and step apply to both the "from" and the "to" field.
 */
final class RangeAttributeType implements AttributeTypeInterface, FormattableAttributeTypeInterface
{
    public function __construct(
        private readonly NumberAttributeType $numberAttributeType,
        private readonly AttributeDisplayFormatter $displayFormatter,
    ) {
    }

    public function getKey(): string
    {
        return AttributeInterface::TYPE_RANGE;
    }

    public function configureForm(FormMetadata $form, AttributeInterface $attribute, string $locale): void
    {
        $this->numberAttributeType->configureForm($form, $attribute, $locale);
    }

    /**
     * Open on one side renders as "≥ from" or "≤ to". The display format may place the bounds with
     * %from% and %to%.
     */
    public function formatValue(AttributeInterface $attribute, mixed $value, string $locale): ?string
    {
        $from = \is_array($value) && \is_float($value['from'] ?? null) ? (string) $value['from'] : '';
        $to = \is_array($value) && \is_float($value['to'] ?? null) ? (string) $value['to'] : '';

        $range = match (true) {
            '' !== $from && '' !== $to => $from . ' – ' . $to,
            '' !== $from => '≥ ' . $from,
            '' !== $to => '≤ ' . $to,
            default => null,
        };

        return $this->displayFormatter->format($attribute, $range, ['%from%' => $from, '%to%' => $to]);
    }

    public function hasDisplayFormat(): bool
    {
        return true;
    }
}
