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
use Sulu\Product\Application\Message\CreateProductFamilyMessage;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;

final class CreateProductFamilyMessageHandler
{
    public function __construct(
        private ProductFamilyRepositoryInterface $productFamilyRepository,
        private ProductFamilyMapperInterface $productFamilyMapper,
    ) {
    }

    public function __invoke(CreateProductFamilyMessage $message): ProductFamilyInterface
    {
        $family = $this->productFamilyRepository->create();

        $this->productFamilyMapper->mapProductFamilyData($family, $message);

        $this->productFamilyRepository->save($family);

        return $family;
    }
}
