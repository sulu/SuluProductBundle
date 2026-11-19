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

class InvalidMainCategoryException extends \Exception implements TranslationErrorMessageExceptionInterface
{
    public function __construct(
        private readonly int $categoryId,
        private readonly string $rootKey,
    ) {
        parent::__construct(\sprintf(
            'The category %d is not below the category "%s" configured as "sulu_product.main_category.root_key".',
            $categoryId,
            $rootKey,
        ));
    }

    public function getMessageTranslationKey(): string
    {
        return 'sulu_product.invalid_main_category';
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessageTranslationParameters(): array
    {
        return ['{id}' => $this->categoryId, '{rootKey}' => $this->rootKey];
    }
}
