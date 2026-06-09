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

use Sulu\Product\Application\Message\RemoveAttributeSetMessage;
use Sulu\Product\Domain\Exception\AttributeSetNotFoundException;
use Sulu\Product\Domain\Repository\AttributeSetRepositoryInterface;

final class RemoveAttributeSetMessageHandler
{
    public function __construct(private AttributeSetRepositoryInterface $attributeSetRepository)
    {
    }

    public function __invoke(RemoveAttributeSetMessage $message): void
    {
        $attributeSet = $this->attributeSetRepository->findOneBy(['uuid' => $message->getUuid()]);

        if (null === $attributeSet) {
            throw new AttributeSetNotFoundException(['uuid' => $message->getUuid()]);
        }

        $this->attributeSetRepository->remove($attributeSet);
    }
}
