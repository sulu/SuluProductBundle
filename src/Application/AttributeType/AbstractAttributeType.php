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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Domain\Model\AttributeInterface;
use Webmozart\Assert\Assert;

abstract class AbstractAttributeType implements AttributeTypeInterface
{
    public function configureField(FieldMetadata $field, AttributeInterface $attribute, string $locale): void
    {
    }

    /**
     * Passes the attribute's configured "min", "max" and "step" on to a numeric field.
     */
    protected function addNumberOptions(FieldMetadata $field, AttributeInterface $attribute): void
    {
        $config = $attribute->getConfig();

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
