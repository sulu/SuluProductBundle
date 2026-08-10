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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Reference\ProductAssociationReferenceCleanupSubscriber;

#[CoversClass(ProductAssociationReferenceCleanupSubscriber::class)]
final class ProductAssociationReferenceCleanupSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<ReferenceRepositoryInterface>
     */
    private ObjectProphecy $referenceRepository;

    private ProductAssociationReferenceCleanupSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->referenceRepository = $this->prophesize(ReferenceRepositoryInterface::class);

        $this->subscriber = new ProductAssociationReferenceCleanupSubscriber(
            $this->referenceRepository->reveal(),
        );
    }

    public function testGetSubscribedEvents(): void
    {
        self::assertSame(
            [ProductRemovedEvent::class => 'onProductRemoved'],
            ProductAssociationReferenceCleanupSubscriber::getSubscribedEvents(),
        );
    }

    public function testOnProductRemovedRemovesReferencesPointingAtTheRemovedProduct(): void
    {
        $this->subscriber->onProductRemoved(new ProductRemovedEvent('uuid-b', 'Removed Product'));

        $this->referenceRepository->removeBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => 'uuid-b',
            'referenceResourceKey' => ProductInterface::RESOURCE_KEY,
        ])->shouldHaveBeenCalledOnce();
    }

    public function testOnProductRemovedDoesNotFlushBecauseTheEventIsDispatchedFromPostFlush(): void
    {
        $this->subscriber->onProductRemoved(new ProductRemovedEvent('uuid-b', 'Removed Product'));

        $this->referenceRepository->flush()->shouldNotHaveBeenCalled();
        $this->referenceRepository->findFlatBy(Argument::cetera())->shouldNotHaveBeenCalled();
    }
}
