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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Reference;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Reference\ProductAssociationReferenceCleanupSubscriber;
use Sulu\Product\Infrastructure\Sulu\Reference\ProductReferenceRefresher;

#[CoversClass(ProductAssociationReferenceCleanupSubscriber::class)]
final class ProductAssociationReferenceCleanupSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<ReferenceRepositoryInterface>
     */
    private ObjectProphecy $referenceRepository;

    /**
     * @var ObjectProphecy<ProductReferenceRefresher>
     */
    private ObjectProphecy $referenceRefresher;

    /**
     * @var ObjectProphecy<EntityManagerInterface>
     */
    private ObjectProphecy $entityManager;

    private ProductAssociationReferenceCleanupSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->referenceRepository = $this->prophesize(ReferenceRepositoryInterface::class);
        $this->referenceRefresher = $this->prophesize(ProductReferenceRefresher::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->subscriber = new ProductAssociationReferenceCleanupSubscriber(
            $this->referenceRepository->reveal(),
            $this->referenceRefresher->reveal(),
            $this->entityManager->reveal(),
        );
    }

    public function testGetSubscribedEvents(): void
    {
        self::assertSame(
            [ProductRemovedEvent::class => 'onProductRemoved'],
            ProductAssociationReferenceCleanupSubscriber::getSubscribedEvents(),
        );
    }

    public function testOnProductRemovedRefreshesEachDistinctReferrer(): void
    {
        $removedProductId = 'uuid-b';

        $this->referenceRepository->findFlatBy(Argument::cetera())->willReturn([
            ['referenceResourceId' => 'uuid-a', 'referenceLocale' => 'en', 'referenceContext' => 'live'],
            ['referenceResourceId' => 'uuid-d', 'referenceLocale' => 'de', 'referenceContext' => 'draft'],
        ]);

        $this->referenceRefresher->refresh([
            'resourceId' => 'uuid-a',
            'resourceKey' => ProductDimensionContentInterface::RESOURCE_KEY,
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn($this->emptyGenerator());

        $this->referenceRefresher->refresh([
            'resourceId' => 'uuid-d',
            'resourceKey' => ProductDimensionContentInterface::RESOURCE_KEY,
            'locale' => 'de',
            'stage' => 'draft',
        ])->willReturn($this->emptyGenerator());

        $this->subscriber->onProductRemoved(new ProductRemovedEvent($removedProductId, 'Removed Product'));

        $this->referenceRepository->findFlatBy(
            [
                'resourceKey' => ProductInterface::RESOURCE_KEY,
                'resourceId' => $removedProductId,
                'referenceResourceKey' => ProductDimensionContentInterface::RESOURCE_KEY,
            ],
            [],
            ['referenceResourceId', 'referenceLocale', 'referenceContext'],
            true,
        )->shouldHaveBeenCalledOnce();

        $this->referenceRefresher->refresh([
            'resourceId' => 'uuid-a',
            'resourceKey' => ProductDimensionContentInterface::RESOURCE_KEY,
            'locale' => 'en',
            'stage' => 'live',
        ])->shouldHaveBeenCalledOnce();

        $this->referenceRefresher->refresh([
            'resourceId' => 'uuid-d',
            'resourceKey' => ProductDimensionContentInterface::RESOURCE_KEY,
            'locale' => 'de',
            'stage' => 'draft',
        ])->shouldHaveBeenCalledOnce();

        $this->referenceRepository->flush()->shouldHaveBeenCalledOnce();
        $this->entityManager->clear()->shouldHaveBeenCalledOnce();
    }

    public function testOnProductRemovedSkipsTheRemovedProductItself(): void
    {
        $removedProductId = 'uuid-b';

        $this->referenceRepository->findFlatBy(Argument::cetera())->willReturn([
            ['referenceResourceId' => $removedProductId, 'referenceLocale' => 'en', 'referenceContext' => 'live'],
        ]);

        $this->subscriber->onProductRemoved(new ProductRemovedEvent($removedProductId, 'Removed Product'));

        $this->referenceRefresher->refresh(Argument::any())->shouldNotHaveBeenCalled();
        $this->referenceRepository->flush()->shouldNotHaveBeenCalled();
    }

    public function testOnProductRemovedIsNoopWithoutInboundReferences(): void
    {
        $this->referenceRepository->findFlatBy(Argument::cetera())->willReturn([]);

        $this->subscriber->onProductRemoved(new ProductRemovedEvent('uuid-b', 'Removed Product'));

        $this->referenceRefresher->refresh(Argument::any())->shouldNotHaveBeenCalled();
        $this->referenceRepository->flush()->shouldNotHaveBeenCalled();
    }

    private function emptyGenerator(): \Generator
    {
        return (static function(): \Generator {
            yield from [];
        })();
    }
}
