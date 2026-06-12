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
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\HttpKernel\SuluProductBundle;

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

    public function testContainerRegistersProductMappers(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertTrue($container->has('sulu_product.product_details_mapper'));
    }

    public function testBundleClassExists(): void
    {
        $this->assertTrue(\class_exists(SuluProductBundle::class));
    }
}
