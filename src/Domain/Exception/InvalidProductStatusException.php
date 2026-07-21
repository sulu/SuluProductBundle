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

class InvalidProductStatusException extends \Exception implements TranslationErrorMessageExceptionInterface
{
    private string $status;

    /**
     * @param array<int, string> $allowedStatuses
     */
    public function __construct(string $status, array $allowedStatuses)
    {
        parent::__construct(\sprintf(
            'The product status "%s" is not one of the configured statuses: %s.',
            $status,
            \implode(', ', $allowedStatuses),
        ));

        $this->status = $status;
    }

    public function getMessageTranslationKey(): string
    {
        return 'sulu_product.invalid_status';
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessageTranslationParameters(): array
    {
        return ['{status}' => $this->status];
    }
}
