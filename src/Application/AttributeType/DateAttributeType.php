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
use Sulu\Product\Domain\Model\AttributeInterface;

final class DateAttributeType implements AttributeTypeInterface, FormattableAttributeTypeInterface
{
    public function getKey(): string
    {
        return AttributeInterface::TYPE_DATE;
    }

    public function configureForm(FormMetadata $form, AttributeInterface $attribute, string $locale): void
    {
    }

    /**
     * The display format is an ICU skeleton such as `yMMMM`, which the locale arranges, so month names and
     * their order follow the website language; without one the locale's medium date, because a bare
     * `05.03.2024` names a different month elsewhere. Rendered in UTC, because a date is stored as midnight UTC.
     */
    public function formatValue(AttributeInterface $attribute, mixed $value, string $locale): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $skeleton = $attribute->getConfig()['displayFormat'] ?? null;
        $pattern = \is_string($skeleton) && '' !== $skeleton
            ? (new \IntlDatePatternGenerator($locale))->getBestPattern($skeleton) ?: null
            : null;

        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, 'UTC', null, $pattern);

        return $formatter->format(new \DateTimeImmutable($value, new \DateTimeZone('UTC'))) ?: null;
    }

    public function hasDisplayFormat(): bool
    {
        return true;
    }
}
