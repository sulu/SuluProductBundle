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

use Sulu\Product\Application\Mapper\ProductFamilyMapperInterface;
use Sulu\Product\Application\Message\ModifyProductFamilyMessage;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;

/**
 * @internal This class should not be instantiated by a project.
 *           Create a ProductFamilyMapper to extend this Handler.
 */
final class ModifyProductFamilyMessageHandler
{
    public function __construct(
        private ProductFamilyRepositoryInterface $productFamilyRepository,
        /** @var iterable<ProductFamilyMapperInterface> */
        private iterable $productFamilyMappers,
    ) {
    }

    public function __invoke(ModifyProductFamilyMessage $message): ProductFamilyInterface
    {
        $family = $this->productFamilyRepository->getOneBy(['uuid' => $message->getUuid()]);

        foreach ($this->productFamilyMappers as $productFamilyMapper) {
            $productFamilyMapper->mapProductFamilyData($family, $message);
        }

        $this->productFamilyRepository->save($family);

        return $family;
    }
}
