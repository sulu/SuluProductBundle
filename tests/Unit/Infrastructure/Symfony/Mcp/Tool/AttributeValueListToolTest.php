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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Application\Ai\GetAttributeValues;
use Sulu\Product\Application\Attribute\ProductAttributeValueFormatter;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductAttributeValueRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\AttributeValueListTool;

#[CoversClass(AttributeValueListTool::class)]
class AttributeValueListToolTest extends TestCase
{
    use ProphecyTrait;

    public function testListValuesDelegatesToGetAttributeValues(): void
    {
        $attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $attributeRepository->findOneBy(['key' => 'unknown'])->willReturn(null)->shouldBeCalled();
        $valueRepository = $this->prophesize(ProductAttributeValueRepositoryInterface::class);
        $valueRepository->countValues(Argument::cetera())->shouldNotBeCalled();

        $tool = new AttributeValueListTool(new GetAttributeValues(
            $attributeRepository->reveal(),
            $valueRepository->reveal(),
            new ProductAttributeValueFormatter(new MeasurementRegistry()),
        ));

        $result = $tool->listValues('unknown', 'en');

        $this->assertSame('unknown_attribute', $result['status']);
    }
}
