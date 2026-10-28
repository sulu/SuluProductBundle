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

interface AttributeTypeInterface
{
    public function getKey(): string;

    /**
     * Adds what the fields of the form fragment `product_attribute_<key>` need for this attribute,
     * e.g. a number's bounds or a select's choices.
     */
    public function configureForm(FormMetadata $form, AttributeInterface $attribute, string $locale): void;
}
