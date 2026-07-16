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

class InvalidVariantAttributeException extends \Exception implements TranslationErrorMessageExceptionInterface
{
    private int $attributeId;

    public function __construct(int $attributeId)
    {
        parent::__construct(\sprintf(
            'The attribute "%d" cannot be marked as a variant attribute because it is not enabled.',
            $attributeId,
        ));

        $this->attributeId = $attributeId;
    }

    public function getMessageTranslationKey(): string
    {
        return 'sulu_product.invalid_variant_attribute';
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessageTranslationParameters(): array
    {
        return ['{attributeId}' => $this->attributeId];
    }
}
