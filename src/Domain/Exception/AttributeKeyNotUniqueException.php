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

class AttributeKeyNotUniqueException extends \Exception implements TranslationErrorMessageExceptionInterface
{
    public function __construct(private string $attributeKey)
    {
        parent::__construct(\sprintf('The attribute key "%s" is already in use.', $attributeKey));
    }

    public function getAttributeKey(): string
    {
        return $this->attributeKey;
    }

    public function getMessageTranslationKey(): string
    {
        return 'sulu_product.attribute_key_already_used';
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessageTranslationParameters(): array
    {
        return ['{key}' => $this->attributeKey];
    }
}
