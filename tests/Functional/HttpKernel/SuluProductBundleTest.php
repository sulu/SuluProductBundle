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

namespace Sulu\Product\Tests\Functional\HttpKernel;

use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\WorkflowTransitionRequest\Event\WorkflowTransitionRequestActionEvent;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Activity\ProductWorkflowTransitionRequestSubscriber;
use Sulu\Product\Infrastructure\Symfony\HttpKernel\SuluProductBundle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SuluProductBundleTest extends SuluTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testContainerRegistersRepositories(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertTrue($container->has(ProductRepositoryInterface::class));
        $this->assertTrue($container->has(AttributeRepositoryInterface::class));
    }

    public function testRegistersTheWorkflowTransitionRequestSubscriber(): void
    {
        self::bootKernel();

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');

        $subscribers = [];
        foreach ($dispatcher->getListeners(WorkflowTransitionRequestActionEvent::class) as $listener) {
            if (\is_array($listener)) {
                $subscribers[] = $listener[0]::class;
            }
        }

        $this->assertContains(ProductWorkflowTransitionRequestSubscriber::class, $subscribers);
    }

    public function testBundleClassExists(): void
    {
        $this->assertTrue(\class_exists(SuluProductBundle::class));
    }
}
