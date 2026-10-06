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

use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\Security\WorkflowTransitionRequestSecurityContextResolverInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Symfony\HttpKernel\SuluProductBundle;

#[CoversClass(SuluProductBundle::class)]
class ProductWorkflowSecurityContextTest extends SuluTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    /**
     * The workflow resolver returns the product security context for the product resource. Without the
     * context on the resource, a publish needs no permission: view, add and edit are enough.
     */
    public function testAProductWorkflowTransitionIsAuthorizedAgainstTheProductContext(): void
    {
        self::bootKernel();

        /** @var WorkflowTransitionRequestSecurityContextResolverInterface $resolver */
        $resolver = self::getContainer()->get(WorkflowTransitionRequestSecurityContextResolverInterface::class);

        $condition = $resolver->resolve(ProductInterface::RESOURCE_KEY, 'a-product-uuid', 'en');

        $this->assertSame(ProductAdmin::SECURITY_CONTEXT, $condition->getSecurityContext());
    }
}
