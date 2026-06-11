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

use Sulu\Product\Application\Mapper\AttributeMapper;
use Sulu\Product\Application\Message\ModifyAttributeMessage;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class ModifyAttributeMessageHandler
{
    public function __construct(
        private AttributeRepositoryInterface $attributeRepository,
        private AttributeMapper $attributeMapper,
    ) {
    }

    public function __invoke(ModifyAttributeMessage $message): AttributeInterface
    {
        $attribute = $this->attributeRepository->getOneBy($message->getIdentifier());
        $this->attributeMapper->mapAttributeData($attribute, $message);

        $this->attributeRepository->save($attribute);

        return $attribute;
    }
}
