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

final class MultiOptionsAttributeType implements AttributeTypeInterface
{
    public function __construct(
        private readonly OptionsAttributeType $optionsAttributeType,
    ) {
    }

    public function getKey(): string
    {
        return AttributeInterface::TYPE_MULTI_OPTIONS;
    }

    public function configureForm(FormMetadata $form, AttributeInterface $attribute, string $locale): void
    {
        $this->optionsAttributeType->configureForm($form, $attribute, $locale);
    }
}
