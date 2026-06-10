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
use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class CreateAttributeMessageHandler
{
    public function __construct(
        private AttributeRepositoryInterface $attributeRepository,
        private AttributeMapper $attributeMapper,
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
    ) {
    }

    public function __invoke(CreateAttributeMessage $message): AttributeInterface
    {
        /** @var AttributeGroupInterface $attributeGroup */
        $attributeGroup = $this->attributeGroupRepository->findOneBy(['uuid' => $message->getAttributeGroup()]);

        $attribute = $this->attributeRepository->create($attributeGroup);
        $this->attributeMapper->mapAttributeData($attribute, $message);

        $this->attributeRepository->save($attribute);
        $this->attributeGroupRepository->save($attributeGroup);

        return $attribute;
    }
}
