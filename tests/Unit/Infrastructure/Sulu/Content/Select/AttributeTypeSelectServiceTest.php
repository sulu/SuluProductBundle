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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\Select;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\AttributeType\AttributeTypeInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Select\AttributeTypeSelectService;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(AttributeTypeSelectService::class)]
class AttributeTypeSelectServiceTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TranslatorInterface> */
    private ObjectProphecy $translator;

    protected function setUp(): void
    {
        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->translator->trans(
            'sulu_product.type_text',
            [],
            'admin',
            'en'
        )->willReturn('Text');
        $this->translator->trans(
            'sulu_product.type_number',
            [],
            'admin',
            'en'
        )->willReturn('Number');
    }

    public function testGetValuesEmpty(): void
    {
        $service = new AttributeTypeSelectService([], $this->translator->reveal());

        $this->assertSame([], $service->getValues('en'));
    }

    public function testGetValuesReturnsKeyAndTranslatedTitle(): void
    {
        $textType = $this->prophesize(AttributeTypeInterface::class);
        $textType->getKey()->willReturn('text');

        $numberType = $this->prophesize(AttributeTypeInterface::class);
        $numberType->getKey()->willReturn('number');

        $service = new AttributeTypeSelectService(
            [$textType->reveal(), $numberType->reveal()],
            $this->translator->reveal()
        );

        $this->assertSame([
            ['name' => 'text', 'title' => 'Text'],
            ['name' => 'number', 'title' => 'Number'],
        ], $service->getValues('en'));
    }

    public function testGetValuesAcceptsGeneratorIterable(): void
    {
        $textType = $this->prophesize(AttributeTypeInterface::class);
        $textType->getKey()->willReturn('text');

        $generator = (static function() use ($textType) {
            yield $textType->reveal();
        })();

        $service = new AttributeTypeSelectService($generator, $this->translator->reveal());

        $this->assertSame([
            ['name' => 'text', 'title' => 'Text'],
        ], $service->getValues('en'));
    }
}
