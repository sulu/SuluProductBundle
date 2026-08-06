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

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataVisitorInterface;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\ConstMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\PropertyMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\SchemaMetadata;
use Sulu\Bundle\AdminBundle\Metadata\SchemaMetadata\StringMetadata;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * A plain product must carry a code, a product with variants may leave it empty — the variants
 * carry their own. Expressed as a schema branch on `type` instead of `mandatory="true"` so it
 * follows the type select live, and with a non-empty string constraint because a bare `required`
 * would accept the `null` that a code-less product loads with.
 *
 * @internal
 */
class ProductCodeFormMetadataVisitor implements FormMetadataVisitorInterface
{
    private const FORM_KEY = 'product_details';

    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (self::FORM_KEY !== $formMetadata->getKey()) {
            return;
        }

        $formMetadata->setSchema($formMetadata->getSchema()->merge(new SchemaMetadata([], [
            new SchemaMetadata([
                new PropertyMetadata('type', false, new ConstMetadata(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)),
            ]),
            new SchemaMetadata([
                new PropertyMetadata('type', false, new ConstMetadata(ProductInterface::TYPE_PRODUCT)),
                new PropertyMetadata('code', true, new StringMetadata(1)),
            ]),
        ])));
    }
}
