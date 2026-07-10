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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Content\Application\ContentCopier\ContentCopierInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Message\RestoreProductVersionMessage;
use Sulu\Product\Application\MessageHandler\RestoreProductVersionMessageHandler;
use Sulu\Product\Domain\Event\ProductVersionRestoredEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

#[CoversClass(RestoreProductVersionMessageHandler::class)]
class RestoreProductVersionMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ContentCopierInterface> */
    private ObjectProphecy $contentCopier;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    private RestoreProductVersionMessageHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentCopier = $this->prophesize(ContentCopierInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $this->handler = new RestoreProductVersionMessageHandler(
            $this->productRepository->reveal(),
            $this->contentCopier->reveal(),
            $this->domainEventCollector->reveal(),
        );
    }

    public function testRestoreVersionUsesDefaultDraftStage(): void
    {
        $product = new Product('prod-uuid');

        $resultDimensionContent = new ProductDimensionContent($product);
        $resultDimensionContent->setLocale('en');
        $resultDimensionContent->setStage(DimensionContentInterface::STAGE_DRAFT);

        $this->productRepository->getOneBy(
            Argument::that(fn (array $filters) => isset($filters['uuid']) && 'prod-uuid' === $filters['uuid']),
            Argument::that(function(array $selects): bool {
                if (!isset($selects[ProductRepositoryInterface::SELECT_PRODUCT_CONTENT])) {
                    return false;
                }

                $select = $selects[ProductRepositoryInterface::SELECT_PRODUCT_CONTENT];
                if (!\is_array($select)) {
                    return false;
                }

                $dimensionAttributes = $select['dimensionAttributes'] ?? null;
                if (!\is_array($dimensionAttributes)) {
                    return false;
                }

                $version = $dimensionAttributes['version'] ?? null;
                if (!\is_array($version)) {
                    return false;
                }

                return DimensionContentInterface::STAGE_DRAFT === ($dimensionAttributes['stage'] ?? null)
                    && 'en' === ($dimensionAttributes['locale'] ?? null)
                    && \in_array(5, $version, true)
                    && \in_array(DimensionContentInterface::CURRENT_VERSION, $version, true);
            })
        )
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->contentCopier->copy(
            $product,
            [
                'stage' => DimensionContentInterface::STAGE_DRAFT,
                'locale' => 'en',
                'version' => 5,
            ],
            $product,
            [
                'stage' => DimensionContentInterface::STAGE_DRAFT,
                'locale' => 'en',
                'version' => DimensionContentInterface::CURRENT_VERSION,
            ],
            ['ignoredAttributes' => ['url']]
        )
            ->shouldBeCalledOnce()
            ->willReturn($resultDimensionContent);

        $this->domainEventCollector->collect(Argument::type(ProductVersionRestoredEvent::class))
            ->shouldBeCalledOnce();

        $message = new RestoreProductVersionMessage(['uuid' => 'prod-uuid'], 5, 'en');

        $result = ($this->handler)($message);

        $this->assertSame($product, $result);
    }

    public function testRestoreVersionHonorsCustomStageFromOptions(): void
    {
        $product = new Product('prod-uuid');

        $resultDimensionContent = new ProductDimensionContent($product);
        $resultDimensionContent->setLocale('de');
        $resultDimensionContent->setStage(DimensionContentInterface::STAGE_LIVE);

        $this->productRepository->getOneBy(
            Argument::type('array'),
            Argument::that(function(array $selects): bool {
                $select = $selects[ProductRepositoryInterface::SELECT_PRODUCT_CONTENT] ?? null;
                if (!\is_array($select)) {
                    return false;
                }

                $dimensionAttributes = $select['dimensionAttributes'] ?? null;
                if (!\is_array($dimensionAttributes)) {
                    return false;
                }

                return DimensionContentInterface::STAGE_LIVE === ($dimensionAttributes['stage'] ?? null);
            })
        )
            ->shouldBeCalledOnce()
            ->willReturn($product);

        $this->contentCopier->copy(
            $product,
            [
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'locale' => 'de',
                'version' => 7,
            ],
            $product,
            [
                'stage' => DimensionContentInterface::STAGE_LIVE,
                'locale' => 'de',
                'version' => DimensionContentInterface::CURRENT_VERSION,
            ],
            ['ignoredAttributes' => ['url']]
        )
            ->shouldBeCalledOnce()
            ->willReturn($resultDimensionContent);

        $this->domainEventCollector->collect(Argument::type(ProductVersionRestoredEvent::class))
            ->shouldBeCalledOnce();

        $message = new RestoreProductVersionMessage(
            ['uuid' => 'prod-uuid'],
            7,
            'de',
            ['stage' => DimensionContentInterface::STAGE_LIVE],
        );

        $result = ($this->handler)($message);

        $this->assertSame($product, $result);
    }
}
