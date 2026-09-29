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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Application\Ai\GetAttributeValues;
use Sulu\Product\Application\Attribute\ProductAttributeValueFormatter;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductAttributeValueRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Ai\Tool\GetAttributeValuesTool;

/**
 * GetAttributeValues (final, so Prophecy can't double it directly) is real here, built over
 * mocked repositories; GetAttributeValuesTest covers its actual behavior in depth, this just
 * proves the adapter threads its arguments through.
 */
#[CoversClass(GetAttributeValuesTool::class)]
class GetAttributeValuesToolTest extends TestCase
{
    use ProphecyTrait;

    public function testInvokeDelegatesToGetAttributeValues(): void
    {
        $attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $attributeRepository->findOneBy(Argument::cetera())->shouldNotBeCalled();
        $productAttributeValueRepository = $this->prophesize(ProductAttributeValueRepositoryInterface::class);

        $tool = new GetAttributeValuesTool(new GetAttributeValues(
            $attributeRepository->reveal(),
            $productAttributeValueRepository->reveal(),
            new ProductAttributeValueFormatter(new MeasurementRegistry()),
        ));

        $result = $tool('  ', 'en');

        $this->assertSame('unknown_attribute', $result['status']);
    }
}
