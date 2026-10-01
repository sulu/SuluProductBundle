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

class ProductVariantParentNotFoundException extends \Exception implements TranslationErrorMessageExceptionInterface
{
    public function __construct(
        private string $variantUuid,
        private string $parentUuid,
    ) {
        parent::__construct(
            \sprintf('The variant "%s" cannot be restored because its parent product "%s" does not exist.', $variantUuid, $parentUuid),
        );
    }

    public function getVariantUuid(): string
    {
        return $this->variantUuid;
    }

    public function getParentUuid(): string
    {
        return $this->parentUuid;
    }

    public function getMessageTranslationKey(): string
    {
        return 'sulu_product.variant_parent_not_found';
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessageTranslationParameters(): array
    {
        return [];
    }
}
