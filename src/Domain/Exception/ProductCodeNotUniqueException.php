<?php

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

class ProductCodeNotUniqueException extends \Exception implements TranslationErrorMessageExceptionInterface
{
    private string $productCode;

    public function __construct(string $productCode)
    {
        parent::__construct(\sprintf('A product with the code "%s" is already in use.', $productCode));

        $this->productCode = $productCode;
    }

    public function getMessageTranslationKey(): string
    {
        return 'sulu_product.code_already_used';
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessageTranslationParameters(): array
    {
        return ['{code}' => $this->productCode];
    }
}
