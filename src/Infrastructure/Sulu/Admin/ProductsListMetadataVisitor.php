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
use Sulu\Product\Application\Routing\ProductSlugProviderInterface;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Fills the status filter options from the configured statuses, which cannot be hardcoded in the
 * list XML because projects can configure their own. The type filter offers only the types the
 * list shows: the admin list, which sends `excludeVariants`, shows no variant, a selection only
 * the types that own a route.
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
        private readonly ProductSlugProviderInterface $slugProvider,
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

        $typeField = $listMetadata->getField('type');
        $typeParameters = $typeField->getFilterTypeParameters() ?? [];
        $typeOptions = $typeParameters['options'] ?? null;
        if (\is_array($typeOptions)) {
            $excludeVariants = \filter_var($metadataOptions['excludeVariants'] ?? false, \FILTER_VALIDATE_BOOLEAN);
            foreach (\array_keys($typeOptions) as $type) {
                if ($excludeVariants ? ProductInterface::TYPE_VARIANT === $type : !$this->slugProvider->ownsRoute((string) $type)) {
                    unset($typeOptions[$type]);
                }
            }
            $typeField->setFilterTypeParameters([...$typeParameters, 'options' => $typeOptions]);
        }
    }
}
