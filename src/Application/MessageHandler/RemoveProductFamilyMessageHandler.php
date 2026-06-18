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

use Sulu\Product\Application\Message\RemoveProductFamilyMessage;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;

final class RemoveProductFamilyMessageHandler
{
    public function __construct(
        private ProductFamilyRepositoryInterface $productFamilyRepository,
    ) {
    }

    public function __invoke(RemoveProductFamilyMessage $message): void
    {
        $family = $this->productFamilyRepository->findOneBy(['uuid' => $message->getUuid()]);

        if (null === $family) {
            throw new ProductFamilyNotFoundException(['uuid' => $message->getUuid()]);
        }

        $this->productFamilyRepository->remove($family);
    }
}
