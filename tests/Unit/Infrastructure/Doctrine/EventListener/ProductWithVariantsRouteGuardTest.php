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

namespace Sulu\Product\Tests\Unit\Infrastructure\Doctrine\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Doctrine\EventListener\ProductWithVariantsRouteGuard;
use Sulu\Route\Domain\Model\Route;

#[CoversClass(ProductWithVariantsRouteGuard::class)]
class ProductWithVariantsRouteGuardTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<UnitOfWork>
     */
    private ObjectProphecy $unitOfWork;

    /**
     * @var ObjectProphecy<EntityManagerInterface>
     */
    private ObjectProphecy $entityManager;

    protected function setUp(): void
    {
        $this->unitOfWork = $this->prophesize(UnitOfWork::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->entityManager->getUnitOfWork()->willReturn($this->unitOfWork->reveal());
        $this->entityManager->getClassMetadata(Argument::type('string'))
            ->willReturn(new ClassMetadata(ProductDimensionContent::class));
    }

    public function testDropsTheRouteOfAProductWithVariants(): void
    {
        $dimensionContent = $this->createDimensionContent(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $route = $this->createRoute();
        $dimensionContent->setRoute($route);

        $this->schedule(insertions: [$dimensionContent]);
        $this->unitOfWork->isScheduledForInsert($route)->willReturn(true);
        // computeChangeSet() would replace the insert's full changeset with a one-field diff.
        $this->unitOfWork->computeChangeSet(Argument::cetera())->shouldNotBeCalled();
        $this->unitOfWork->recomputeSingleEntityChangeSet(Argument::any(), $dimensionContent)->shouldBeCalled();
        $this->entityManager->detach($route)->shouldBeCalled();

        $this->guard()->onFlush(new OnFlushEventArgs($this->entityManager->reveal()));

        $this->assertNull($dimensionContent->getRoute());
    }

    public function testDropsTheRouteOnUpdateWithoutDetachingAPersistedRoute(): void
    {
        $dimensionContent = $this->createDimensionContent(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $route = $this->createRoute();
        $dimensionContent->setRoute($route);

        $this->schedule(updates: [$dimensionContent]);
        $this->unitOfWork->isScheduledForInsert($route)->willReturn(false);
        $this->unitOfWork->recomputeSingleEntityChangeSet(Argument::any(), $dimensionContent)->shouldBeCalled();
        $this->entityManager->detach(Argument::any())->shouldNotBeCalled();

        $this->guard()->onFlush(new OnFlushEventArgs($this->entityManager->reveal()));

        $this->assertNull($dimensionContent->getRoute());
    }

    public function testKeepsTheRouteOfAPlainProduct(): void
    {
        $dimensionContent = $this->createDimensionContent(ProductInterface::TYPE_PRODUCT);
        $route = $this->createRoute();
        $dimensionContent->setRoute($route);

        $this->schedule(insertions: [$dimensionContent]);
        $this->unitOfWork->computeChangeSet(Argument::cetera())->shouldNotBeCalled();
        $this->unitOfWork->recomputeSingleEntityChangeSet(Argument::cetera())->shouldNotBeCalled();
        $this->entityManager->detach(Argument::any())->shouldNotBeCalled();

        $this->guard()->onFlush(new OnFlushEventArgs($this->entityManager->reveal()));

        $this->assertSame($route, $dimensionContent->getRoute());
    }

    public function testIgnoresAProductWithVariantsWithoutRoute(): void
    {
        $dimensionContent = $this->createDimensionContent(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->schedule(insertions: [$dimensionContent]);
        $this->unitOfWork->computeChangeSet(Argument::cetera())->shouldNotBeCalled();
        $this->entityManager->detach(Argument::any())->shouldNotBeCalled();

        $this->guard()->onFlush(new OnFlushEventArgs($this->entityManager->reveal()));

        $this->assertNull($dimensionContent->getRoute());
    }

    public function testIgnoresOtherEntities(): void
    {
        $this->schedule(insertions: [new \stdClass()], updates: [$this->createRoute()]);
        $this->entityManager->detach(Argument::any())->shouldNotBeCalled();

        $this->guard()->onFlush(new OnFlushEventArgs($this->entityManager->reveal()));

        $this->addToAssertionCount(1);
    }

    private function guard(): ProductWithVariantsRouteGuard
    {
        return new ProductWithVariantsRouteGuard();
    }

    private function createDimensionContent(string $type): ProductDimensionContent
    {
        $product = new Product();
        $product->setType($type);

        $dimensionContent = new ProductDimensionContent($product);
        $dimensionContent->setLocale('en');

        return $dimensionContent;
    }

    private function createRoute(): Route
    {
        return new Route('products', '1', 'en', '/products/my-product');
    }

    /**
     * @param object[] $insertions
     * @param object[] $updates
     */
    private function schedule(array $insertions = [], array $updates = []): void
    {
        $this->unitOfWork->getScheduledEntityInsertions()->willReturn($insertions);
        $this->unitOfWork->getScheduledEntityUpdates()->willReturn($updates);
    }
}
