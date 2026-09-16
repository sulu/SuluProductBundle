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

namespace Sulu\Product\Tests\Unit\Application\MessageHandler;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Sulu\Product\Application\Mapper\ProductContentMapper;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Application\MessageHandler\CreateProductMessageHandler;
use Sulu\Product\Domain\Event\ProductCreatedEvent;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CreateProductMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ContentPersisterInterface> */
    private ObjectProphecy $contentPersister;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentPersister = $this->prophesize(ContentPersisterInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{locale: string, productFamily: string, code?: string, author?: int|null}
     */
    private function createData(array $data = []): array
    {
        /** @var array{locale: string, productFamily: string, code?: string, author?: int|null} $data */
        $data = \array_replace(['locale' => 'en', 'productFamily' => 'family-uuid'], $data);

        return $data;
    }

    private function createHandler(?TokenStorageInterface $tokenStorage = null): CreateProductMessageHandler
    {
        return new CreateProductMessageHandler(
            $this->productRepository->reveal(),
            [new ProductContentMapper($this->contentPersister->reveal())],
            $this->domainEventCollector->reveal(),
            $tokenStorage,
        );
    }

    public function testCreateProduct(): void
    {
        $product = new Product();

        $this->productRepository->createNew(null)
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productRepository->add($product)
            ->shouldBeCalledOnce();

        $this->contentPersister->persist($product, Argument::type('array'), Argument::type('array'))
            ->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductCreatedEvent::class))
            ->shouldBeCalledOnce();

        $message = new CreateProductMessage($this->createData());

        $result = ($this->createHandler())($message);

        $this->assertSame($product, $result);
    }

    public function testCreateProductWithoutTokenStorage(): void
    {
        $product = new Product();

        $this->productRepository->createNew(null)->willReturn($product);
        $this->productRepository->add($product)->shouldBeCalled();
        $this->contentPersister->persist(Argument::cetera())->shouldBeCalled();
        $this->domainEventCollector->collect(Argument::type(ProductCreatedEvent::class))->shouldBeCalled();

        $message = new CreateProductMessage($this->createData());

        $result = ($this->createHandler(null))($message);

        $this->assertSame($product, $result);
    }

    public function testCreateDoesNotOverwriteExistingAuthor(): void
    {
        $product = new Product();

        $this->productRepository->createNew(null)->willReturn($product);
        $this->productRepository->add($product)->shouldBeCalledOnce();

        $this->contentPersister->persist(
            $product,
            Argument::that(fn (array $data) => isset($data['author']) && 42 === $data['author']),
            Argument::type('array')
        )->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductCreatedEvent::class))
            ->shouldBeCalledOnce();

        $message = new CreateProductMessage($this->createData(['author' => 42]));

        $result = ($this->createHandler())($message);

        $this->assertSame($product, $result);
    }

    public function testCreateProductSetsAuthorFromToken(): void
    {
        $product = new Product();

        /** @var ObjectProphecy<ContactInterface> $contact */
        $contact = $this->prophesize(ContactInterface::class);
        $contact->getId()->willReturn(5);

        $user = new User();
        $user->setContact($contact->reveal());

        /** @var ObjectProphecy<TokenInterface> $token */
        $token = $this->prophesize(TokenInterface::class);
        $token->getUser()->willReturn($user);

        /** @var ObjectProphecy<TokenStorageInterface> $tokenStorage */
        $tokenStorage = $this->prophesize(TokenStorageInterface::class);
        $tokenStorage->getToken()->willReturn($token->reveal());

        $this->productRepository->createNew(null)
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productRepository->add($product)->shouldBeCalledOnce();

        $this->contentPersister->persist(
            $product,
            Argument::that(fn (array $data) => 5 === $data['author']),
            Argument::type('array')
        )->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductCreatedEvent::class))
            ->shouldBeCalledOnce();

        $message = new CreateProductMessage($this->createData());

        $result = ($this->createHandler($tokenStorage->reveal()))($message);

        $this->assertSame($product, $result);
    }

    public function testCreateProductThrowsOnDuplicateCode(): void
    {
        $this->productRepository->existBy(['code' => 'DUPLICATE-CODE'])
            ->willReturn(true);

        $message = new CreateProductMessage($this->createData(['code' => 'DUPLICATE-CODE']));

        $this->expectException(ProductCodeNotUniqueException::class);

        ($this->createHandler())($message);
    }
}
