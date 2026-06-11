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

namespace Sulu\Product\Application\MessageHandler;

use Sulu\Product\Application\Message\RemoveAttributeMessage;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class RemoveAttributeMessageHandler
{
    public function __construct(private AttributeRepositoryInterface $attributeRepository)
    {
    }

    public function __invoke(RemoveAttributeMessage $message): void
    {
        $attribute = $this->attributeRepository->getOneBy($message->getIdentifier());

        $this->attributeRepository->remove($attribute);
    }
}
