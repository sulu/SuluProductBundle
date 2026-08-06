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

use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\ListMetadata;
use Sulu\Bundle\AdminBundle\Metadata\ListMetadata\ListMetadataVisitorInterface;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Fills the status filter options from the configured product statuses, the same source
 * {@see ProductStatusFormMetadataVisitor} uses for the form's status select — they cannot be
 * hardcoded in the list XML because projects can configure their own statuses.
 *
 * @internal
 */
class ProductsListMetadataVisitor implements ListMetadataVisitorInterface
{
    private const LIST_KEY = ProductInterface::LIST_KEY;

    /**
     * @param array<int, string> $productStatuses
     */
    public function __construct(
        private readonly array $productStatuses,
    ) {
    }

    public function visitListMetadata(ListMetadata $listMetadata, string $key, string $locale, array $metadataOptions = []): void
    {
        if (self::LIST_KEY !== $key) {
            return;
        }

        $statusField = $listMetadata->getField('status');

        $options = [];
        foreach ($this->productStatuses as $status) {
            $options[$status] = 'sulu_product.product_status.' . $status;
        }

        $statusField->setFilterTypeParameters(['options' => $options]);
    }
}
