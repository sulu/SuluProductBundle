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
use Sulu\Product\Application\Mapper\ProductMapperInterface;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Application\MessageHandler\CreateProductMessageHandler;
use Sulu\Product\Domain\Event\ProductCreatedEvent;
use Sulu\Product\Domain\Exception\ProductCodeNotUniqueException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class CreateProductMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;

    /** @var ObjectProphecy<ProductMapperInterface> */
    private ObjectProphecy $productMapper;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    private ProductFamilyInterface $productFamily;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->productMapper = $this->prophesize(ProductMapperInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $this->productFamily = new ProductFamily();
        $this->productFamilyRepository->getOneBy(['uuid' => 'family-uuid'])
            ->willReturn($this->productFamily);
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

    public function testCreateProduct(): void
    {
        $product = new Product($this->productFamily);

        $this->productRepository->createNew($this->productFamily, null)
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productMapper->mapProductData($product, Argument::type('array'))
            ->shouldBeCalledOnce();

        $this->productRepository->add($product)
            ->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductCreatedEvent::class))
            ->shouldBeCalledOnce();

        $handler = new CreateProductMessageHandler(
            $this->productRepository->reveal(),
            $this->productFamilyRepository->reveal(),
            [$this->productMapper->reveal()],
            $this->domainEventCollector->reveal(),
            null,
        );

        $message = new CreateProductMessage($this->createData());

        $result = ($handler)($message);

        $this->assertSame($product, $result);
    }

    public function testCreateProductWithMapper(): void
    {
        $product = new Product($this->productFamily);

        /** @var ObjectProphecy<ProductMapperInterface> $secondMapper */
        $secondMapper = $this->prophesize(ProductMapperInterface::class);

        $this->productRepository->createNew($this->productFamily, null)
            ->willReturn($product);

        $this->productMapper->mapProductData($product, Argument::type('array'))
            ->shouldBeCalledOnce();

        $secondMapper->mapProductData($product, Argument::type('array'))
            ->shouldBeCalledOnce();

        $this->productRepository->add($product)->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductCreatedEvent::class))
            ->shouldBeCalledOnce();

        $handler = new CreateProductMessageHandler(
            $this->productRepository->reveal(),
            $this->productFamilyRepository->reveal(),
            [$this->productMapper->reveal(), $secondMapper->reveal()],
            $this->domainEventCollector->reveal(),
            null,
        );

        $message = new CreateProductMessage($this->createData());

        $result = ($handler)($message);

        $this->assertSame($product, $result);
    }

    public function testCreateProductWithoutTokenStorage(): void
    {
        $product = new Product($this->productFamily);

        $this->productRepository->createNew($this->productFamily, null)->willReturn($product);
        $this->productMapper->mapProductData($product, Argument::type('array'))->shouldBeCalled();
        $this->productRepository->add($product)->shouldBeCalled();
        $this->domainEventCollector->collect(Argument::type(ProductCreatedEvent::class))->shouldBeCalled();

        $handler = new CreateProductMessageHandler(
            $this->productRepository->reveal(),
            $this->productFamilyRepository->reveal(),
            [$this->productMapper->reveal()],
            $this->domainEventCollector->reveal(),
            null,
        );

        $message = new CreateProductMessage($this->createData());

        $result = ($handler)($message);

        $this->assertSame($product, $result);
    }

    public function testCreateDoesNotOverwriteExistingAuthor(): void
    {
        $product = new Product($this->productFamily);

        $this->productRepository->createNew($this->productFamily, null)->willReturn($product);

        $this->productMapper->mapProductData(
            $product,
            Argument::that(fn (array $data) => isset($data['author']) && 42 === $data['author'])
        )->shouldBeCalledOnce();

        $this->productRepository->add($product)->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductCreatedEvent::class))
            ->shouldBeCalledOnce();

        $handler = new CreateProductMessageHandler(
            $this->productRepository->reveal(),
            $this->productFamilyRepository->reveal(),
            [$this->productMapper->reveal()],
            $this->domainEventCollector->reveal(),
            null,
        );

        $message = new CreateProductMessage($this->createData(['author' => 42]));

        $result = ($handler)($message);

        $this->assertSame($product, $result);
    }

    public function testCreateProductSetsAuthorFromToken(): void
    {
        $product = new Product($this->productFamily);

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

        $this->productRepository->createNew($this->productFamily, null)
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productMapper->mapProductData(
            $product,
            Argument::that(fn (array $data) => 5 === $data['author'])
        )->shouldBeCalledOnce();

        $this->productRepository->add($product)->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductCreatedEvent::class))
            ->shouldBeCalledOnce();

        $handler = new CreateProductMessageHandler(
            $this->productRepository->reveal(),
            $this->productFamilyRepository->reveal(),
            [$this->productMapper->reveal()],
            $this->domainEventCollector->reveal(),
            $tokenStorage->reveal(),
        );

        $message = new CreateProductMessage($this->createData());

        $result = ($handler)($message);

        $this->assertSame($product, $result);
    }

    public function testCreateProductThrowsOnDuplicateCode(): void
    {
        $this->productRepository->existBy(['code' => 'DUPLICATE-CODE'])
            ->willReturn(true);

        $handler = new CreateProductMessageHandler(
            $this->productRepository->reveal(),
            $this->productFamilyRepository->reveal(),
            [$this->productMapper->reveal()],
            $this->domainEventCollector->reveal(),
            null,
        );

        $message = new CreateProductMessage($this->createData(['code' => 'DUPLICATE-CODE']));

        $this->expectException(ProductCodeNotUniqueException::class);

        ($handler)($message);
    }
}
