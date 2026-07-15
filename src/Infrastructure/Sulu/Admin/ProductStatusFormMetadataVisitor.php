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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
class ProductStatusFormMetadataVisitor implements FormMetadataVisitorInterface
{
    private const FORM_KEY = 'product_details';

    /**
     * @param array<int, string> $productStatuses
     */
    public function __construct(
        private readonly array $productStatuses,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (self::FORM_KEY !== $formMetadata->getKey()) {
            return;
        }

        $statusField = $formMetadata->getItems()['status'] ?? null;
        if (!$statusField instanceof FieldMetadata) {
            return;
        }

        $values = new OptionMetadata();
        $values->setName('values');
        $values->setType(OptionMetadata::TYPE_COLLECTION);

        foreach ($this->productStatuses as $status) {
            $option = new OptionMetadata();
            $option->setName($status);
            $option->setValue($status);
            $option->setTitle(
                $this->translator->trans('sulu_product.product_status.' . $status, [], 'admin', $locale),
                $locale,
            );
            $values->addValueOption($option);
        }

        $statusField->addOption($values);
    }
}
