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

use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Product\Application\Message\RemoveProductFamilyMessage;
use Sulu\Product\Domain\Event\ProductFamilyRemovedEvent;
use Sulu\Product\Domain\Exception\ProductFamilyHasProductsException;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

final class RemoveProductFamilyMessageHandler
{
    public function __construct(
        private ProductFamilyRepositoryInterface $productFamilyRepository,
        private ProductRepositoryInterface $productRepository,
        private DomainEventCollectorInterface $domainEventCollector,
        private TrashManagerInterface $trashManager,
    ) {
    }

    public function __invoke(RemoveProductFamilyMessage $message): void
    {
        $family = $this->productFamilyRepository->findOneBy(['uuid' => $message->getUuid()]);

        if (null === $family) {
            throw new ProductFamilyNotFoundException(['uuid' => $message->getUuid()]);
        }

        if ($this->productRepository->existBy(['productFamilyUuid' => $message->getUuid()])) {
            throw new ProductFamilyHasProductsException($message->getUuid());
        }

        $this->trashManager->store(ProductFamilyInterface::RESOURCE_KEY, $family);

        $this->productFamilyRepository->remove($family);

        $titleLocale = $family->getDefaultLocale();
        $this->domainEventCollector->collect(new ProductFamilyRemovedEvent(
            $message->getUuid(),
            null !== $titleLocale ? $family->getTranslation($titleLocale)?->getName() : null,
            $titleLocale,
        ));
    }
}
