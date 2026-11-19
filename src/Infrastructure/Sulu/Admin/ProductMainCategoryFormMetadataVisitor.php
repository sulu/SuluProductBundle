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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataVisitorInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Limits the selectable main categories to the children of the `sulu_product.main_category.root_key` category.
 *
 * @internal
 */
class ProductMainCategoryFormMetadataVisitor implements FormMetadataVisitorInterface
{
    public function __construct(
        private readonly ?string $rootKey,
    ) {
    }

    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (null === $this->rootKey || ProductInterface::FORM_KEY !== $formMetadata->getKey()) {
            return;
        }

        $mainCategoryField = $formMetadata->getItems()['mainCategory'] ?? null;
        if (!$mainCategoryField instanceof FieldMetadata) {
            return;
        }

        $rootKeyOption = new OptionMetadata();
        $rootKeyOption->setName('rootKey');
        $rootKeyOption->setValue($this->rootKey);

        $requestParameters = new OptionMetadata();
        $requestParameters->setName('request_parameters');
        $requestParameters->setType(OptionMetadata::TYPE_COLLECTION);
        $requestParameters->addValueOption($rootKeyOption);

        $mainCategoryField->addOption($requestParameters);
    }
}
