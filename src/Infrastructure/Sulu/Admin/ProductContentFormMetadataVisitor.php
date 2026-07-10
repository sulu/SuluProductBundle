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

namespace Sulu\Product\Infrastructure\Sulu\Admin;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadataVisitorInterface;
use Sulu\Product\Domain\Model\ProductDimensionContent;

/**
 * @internal
 */
class ProductContentFormMetadataVisitor implements TypedFormMetadataVisitorInterface
{
    public function visitTypedFormMetadata(TypedFormMetadata $formMetadata, string $key, string $locale, array $metadataOptions = []): void
    {
        if (ProductDimensionContent::getTemplateType() !== $key) {
            return;
        }

        foreach ($formMetadata->getForms() as $form) {
            $titleField = $form->getItems()['title'] ?? null;
            if ($titleField instanceof FieldMetadata) {
                $titleField->setDisabledCondition('true');
            }
        }
    }
}
