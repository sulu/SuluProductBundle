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

namespace Sulu\Product\Domain\Exception;

use Sulu\Component\Rest\Exception\TranslationErrorMessageExceptionInterface;

class ProductFamilyHasProductsException extends \Exception implements TranslationErrorMessageExceptionInterface
{
    public function __construct(private string $productFamilyUuid)
    {
        parent::__construct(
            \sprintf('The product family "%s" cannot be removed because products are still assigned to it.', $productFamilyUuid),
        );
    }

    public function getProductFamilyUuid(): string
    {
        return $this->productFamilyUuid;
    }

    public function getMessageTranslationKey(): string
    {
        return 'sulu_product.product_family_has_products';
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessageTranslationParameters(): array
    {
        return [];
    }
}
