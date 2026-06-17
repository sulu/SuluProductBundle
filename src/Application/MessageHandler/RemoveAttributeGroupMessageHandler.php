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

use Sulu\Product\Application\Message\RemoveAttributeGroupMessage;
use Sulu\Product\Domain\Exception\AttributeGroupNotEmptyException;
use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class RemoveAttributeGroupMessageHandler
{
    public function __construct(
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
        private AttributeRepositoryInterface $attributeRepository,
    ) {
    }

    public function __invoke(RemoveAttributeGroupMessage $message): void
    {
        $group = $this->attributeGroupRepository->findOneBy(['uuid' => $message->getUuid()]);

        if (null === $group) {
            throw new AttributeGroupNotFoundException(['uuid' => $message->getUuid()]);
        }

        $attributeCount = $this->attributeRepository->countBy(['group' => $group]);
        if ($attributeCount > 0) {
            throw new AttributeGroupNotEmptyException($message->getUuid(), $attributeCount);
        }

        $this->attributeGroupRepository->remove($group);
    }
}
