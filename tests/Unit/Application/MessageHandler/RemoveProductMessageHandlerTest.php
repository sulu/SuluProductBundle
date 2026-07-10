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
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Product\Application\Message\RemoveProductMessage;
use Sulu\Product\Application\MessageHandler\RemoveProductMessageHandler;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class RemoveProductMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);
    }

    public function testRemoveProduct(): void
    {
        $product = new Product('prod-uuid');

        $this->productRepository->getOneBy(['uuid' => 'prod-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productRepository->remove($product)
            ->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductRemovedEvent::class))
            ->shouldBeCalledOnce();

        $handler = new RemoveProductMessageHandler(
            $this->productRepository->reveal(),
            $this->domainEventCollector->reveal(),
            null,
        );

        $message = new RemoveProductMessage(['uuid' => 'prod-uuid'], 'en');

        ($handler)($message);
    }

    public function testRemoveProductFallsBackToAvailableLocaleForTitle(): void
    {
        $product = new Product('prod-uuid-fallback');

        // Unlocalized draft content with availableLocales set
        $unlocalizedContent = new ProductDimensionContent($product);
        $unlocalizedContent->addAvailableLocale('en');
        $unlocalizedContent->addAvailableLocale('de');

        // 'en' localized content — no title
        $enContent = new ProductDimensionContent($product);
        $enContent->setLocale('en');

        // 'de' localized content — has title
        $deContent = new ProductDimensionContent($product);
        $deContent->setLocale('de');
        $deContent->setTemplateData(['title' => 'German Title']);

        $product->addDimensionContent($unlocalizedContent);
        $product->addDimensionContent($enContent);
        $product->addDimensionContent($deContent);

        $this->productRepository->getOneBy(['uuid' => 'prod-uuid-fallback'])
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->productRepository->remove($product)
            ->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductRemovedEvent::class))
            ->shouldBeCalledOnce();

        $handler = new RemoveProductMessageHandler(
            $this->productRepository->reveal(),
            $this->domainEventCollector->reveal(),
            null,
        );

        $message = new RemoveProductMessage(['uuid' => 'prod-uuid-fallback'], 'en');

        ($handler)($message);
    }

    public function testRemoveProductWithTrashManager(): void
    {
        $product = new Product('prod-uuid');

        /** @var ObjectProphecy<TrashManagerInterface> $trashManager */
        $trashManager = $this->prophesize(TrashManagerInterface::class);

        $this->productRepository->getOneBy(['uuid' => 'prod-uuid'])
            ->willReturn($product);

        $this->productRepository->remove($product)->shouldBeCalled();

        $trashManager->store(ProductInterface::RESOURCE_KEY, $product)
            ->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::type(ProductRemovedEvent::class))
            ->shouldBeCalled();

        $handler = new RemoveProductMessageHandler(
            $this->productRepository->reveal(),
            $this->domainEventCollector->reveal(),
            $trashManager->reveal(),
        );

        $message = new RemoveProductMessage(['uuid' => 'prod-uuid'], 'en');

        ($handler)($message);
    }
}
