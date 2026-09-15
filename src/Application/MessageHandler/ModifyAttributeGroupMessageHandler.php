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

use Sulu\Product\Application\Message\ModifyAttributeGroupMessage;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;

final class ModifyAttributeGroupMessageHandler
{
    public function __construct(
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
    ) {
    }

    public function __invoke(ModifyAttributeGroupMessage $message): AttributeGroupInterface
    {
        $group = $this->attributeGroupRepository->getOneBy(['uuid' => $message->getUuid()]);

        $translation = $group->getTranslation($message->getLocale());
        if (null === $translation) {
            $translation = new AttributeGroupTranslation($group, $message->getLocale(), $message->getName());
            $group->addTranslation($translation);
        } else {
            $translation->setName($message->getName());
        }
        $translation->setDescription($message->getDescription());

        $this->attributeGroupRepository->save($group);

        return $group;
    }
}
