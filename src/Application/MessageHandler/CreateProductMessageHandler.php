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

use Sulu\Product\Application\Mapper\ProductMapperInterface;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Domain\Event\ProductCreatedEvent;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @internal This class should not be instantiated by a project.
 *           Create a ProductMapper to extend this Handler.
 */
final class CreateProductMessageHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
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

        $code = $data['code'] ?? null;
        if (null !== $code && $this->productRepository->existBy(['code' => $code])) {
            throw new ProductCodeNotUniqueException($code);
        }

        $product = $this->productRepository->createNew($message->getUuid());

        foreach ($this->productMappers as $productMapper) {
            $productMapper->mapProductData($product, $data);
        }

        $this->productRepository->add($product);

        /** @var string $locale */
        $locale = $data['locale'];

        $this->domainEventCollector->collect(new ProductCreatedEvent($product, $locale, $data));

        return $product;
    }
}
