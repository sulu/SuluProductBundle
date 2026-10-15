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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Application\Attribute\AttributeDisplayFormatter;
use Sulu\Product\Domain\Model\AttributeInterface;
use Webmozart\Assert\Assert;

final class NumberAttributeType implements AttributeTypeInterface, FormattableAttributeTypeInterface
{
    public function __construct(
        private readonly AttributeDisplayFormatter $displayFormatter,
    ) {
    }

    public function getKey(): string
    {
        return AttributeInterface::TYPE_NUMBER;
    }

    public function configureForm(FormMetadata $form, AttributeInterface $attribute, string $locale): void
    {
        $config = $attribute->getConfig();

        foreach ($form->getItems() as $field) {
            if (!$field instanceof FieldMetadata) {
                continue;
            }

            foreach (['min', 'max', 'step'] as $name) {
                $value = $config[$name] ?? null;
                if (null === $value) {
                    continue;
                }

                Assert::numeric($value);

                $option = new OptionMetadata();
                $option->setName($name);
                $option->setValue((string) $value);
                $field->addOption($option);
            }
        }
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
