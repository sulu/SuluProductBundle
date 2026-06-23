<?php

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
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Product\Application\Mapper\ProductMapperInterface;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Domain\Event\ProductCreatedEvent;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @internal This class should not be instantiated by a project.
 *           Create a ProductMapper to extend this Handler.
 */
final class CreateProductMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductFamilyRepositoryInterface $productFamilyRepository,
        /** @var iterable<ProductMapperInterface> */
        private iterable $productMappers,
        private DomainEventCollectorInterface $domainEventCollector,
        private ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public function __invoke(CreateProductMessage $message): ProductInterface
    {
        $data = $message->getData();

        if (!\array_key_exists('author', $data)) {
            $token = $this->tokenStorage?->getToken();
            $user = $token?->getUser();
            if (null !== $token && $user instanceof User) {
                $contact = $user->getContact();
                $data['author'] = $contact?->getId();
            }
        }

        if (!\array_key_exists('authored', $data)) {
            $data['authored'] = (new \DateTimeImmutable())->format('c');
        }

        /** @var string|null $code */
        $code = $data['code'] ?? null;
        if (null !== $code && $this->productRepository->existBy(['code' => $code])) {
            throw new ProductCodeNotUniqueException($code);
        }

        $productFamily = $this->productFamilyRepository->getOneBy(['uuid' => $message->getProductFamily()]);

        $product = $this->productRepository->createNew($productFamily, $message->getUuid());

        foreach ($this->productMappers as $productMapper) {
            $productMapper->mapProductData($product, $data);
        }

        $this->productRepository->add($product);

        $this->domainEventCollector->collect(new ProductCreatedEvent($product, $message->getLocale(), $data));

        return $product;
    }
}
