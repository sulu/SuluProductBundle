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

namespace Sulu\Product\Tests\Unit\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\UserInterface\Controller\Admin\ProductComponentController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ProductComponentControllerTest extends TestCase
{
    use ProphecyTrait;

    private ProductComponentController $controller;

    protected function setUp(): void
    {
        $this->controller = new ProductComponentController(
            $this->prophesize(ProductRepositoryInterface::class)->reveal(),
            $this->prophesize(MessageBusInterface::class)->reveal(),
            $this->prophesize(NormalizerInterface::class)->reveal(),
            $this->prophesize(FieldDescriptorFactoryInterface::class)->reveal(),
            $this->prophesize(DoctrineListBuilderFactoryInterface::class)->reveal(),
            $this->prophesize(RestHelperInterface::class)->reveal(),
        );
    }

    public function testGetSecurityContext(): void
    {
        $this->assertSame(ProductAdmin::SECURITY_CONTEXT, $this->controller->getSecurityContext());
    }

    public function testGetLocaleReadsTheQueryBeforeTheRequestLocale(): void
    {
        $this->assertSame('de', $this->controller->getLocale(new Request(['locale' => 'de'])));

        $request = new Request();
        $request->setLocale('fr');
        $this->assertSame('fr', $this->controller->getLocale($request));
    }
}
