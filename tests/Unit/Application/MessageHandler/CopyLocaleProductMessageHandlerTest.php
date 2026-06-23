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
use Sulu\Content\Application\ContentCopier\ContentCopierInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Message\CopyLocaleProductMessage;
use Sulu\Product\Application\MessageHandler\CopyLocaleProductMessageHandler;
use Sulu\Product\Domain\Event\ProductTranslationCopiedEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

class CopyLocaleProductMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ContentCopierInterface> */
    private ObjectProphecy $contentCopier;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    private CopyLocaleProductMessageHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentCopier = $this->prophesize(ContentCopierInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $this->handler = new CopyLocaleProductMessageHandler(
            $this->productRepository->reveal(),
            $this->contentCopier->reveal(),
            $this->domainEventCollector->reveal(),
        );
    }

    public function testCopyLocale(): void
    {
        $product = new Product(new ProductFamily(), 'prod-uuid');

        $targetDimensionContent = new ProductDimensionContent($product);
        $targetDimensionContent->setLocale('de');
        $targetDimensionContent->setStage(DimensionContentInterface::STAGE_DRAFT);

        $this->productRepository->getOneBy(
            Argument::that(fn (array $filters) => isset($filters['uuid']) && 'prod-uuid' === $filters['uuid']),
            Argument::type('array')
        )
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->contentCopier->copy(
            $product,
            ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => 'en'],
            $product,
            ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => 'de'],
            Argument::type('array')
        )
            ->shouldBeCalledOnce()
            ->willReturn($targetDimensionContent);

        $this->domainEventCollector->collect(Argument::type(ProductTranslationCopiedEvent::class))
            ->shouldBeCalledOnce();

        $message = new CopyLocaleProductMessage(['uuid' => 'prod-uuid'], 'en', 'de');

        $result = ($this->handler)($message);

        $this->assertSame($product, $result);
    }
}
