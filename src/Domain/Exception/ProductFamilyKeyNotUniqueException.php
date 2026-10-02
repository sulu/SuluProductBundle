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

class ProductFamilyKeyNotUniqueException extends \Exception implements TranslationErrorMessageExceptionInterface
{
    public function __construct(private readonly string $key)
    {
        parent::__construct(\sprintf('A product family with the key "%s" already exists.', $key));
    }

    public function getMessageTranslationKey(): string
    {
        return 'sulu_product.product_family_key_already_used';
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessageTranslationParameters(): array
    {
        return ['{key}' => $this->key];
    }
}
