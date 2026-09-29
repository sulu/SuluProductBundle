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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Ai\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Application\Ai\GetAttributes;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Ai\Tool\GetAttributesTool;

/**
 * GetAttributes (final, so Prophecy can't double it directly) is real here, built over a mocked
 * repository; GetAttributesTest covers its actual listing behavior in depth, this just proves
 * the adapter threads its arguments through.
 */
#[CoversClass(GetAttributesTool::class)]
class GetAttributesToolTest extends TestCase
{
    use ProphecyTrait;

    public function testInvokeDelegatesToGetAttributes(): void
    {
        $attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $attributeRepository->findBy()->willReturn([]);

        $tool = new GetAttributesTool(new GetAttributes($attributeRepository->reveal(), new MeasurementRegistry(null)));

        $this->assertSame([], $tool('en'));
    }
}
