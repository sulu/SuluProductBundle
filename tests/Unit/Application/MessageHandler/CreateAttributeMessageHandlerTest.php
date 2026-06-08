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

namespace Sulu\Product\Tests\Unit\Application\MessageHandler;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Application\MessageHandler\CreateAttributeMessageHandler;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

class CreateAttributeMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    protected function setUp(): void
    {
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
    }

    public function testCreateAttribute(): void
    {
        $attribute = new Attribute();

        $this->attributeRepository->create()
            ->shouldBeCalledOnce()
            ->willReturn($attribute);

        $this->attributeRepository->save($attribute)
            ->shouldBeCalledOnce();

        $handler = new CreateAttributeMessageHandler($this->attributeRepository->reveal());

        $message = new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'color',
            'name' => 'Color',
            'type' => 'text',
            'description' => 'Color of the product',
        ]);

        $result = ($handler)($message);

        $this->assertSame($attribute, $result);
        $this->assertSame('color', $attribute->getKey());
        $this->assertSame('text', $attribute->getType());

        $translation = $attribute->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('Color', $translation->getName());
        $this->assertSame('Color of the product', $translation->getDescription());
    }

    public function testCreateAttributeWithOptions(): void
    {
        $attribute = new Attribute();

        $this->attributeRepository->create()->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = new CreateAttributeMessageHandler($this->attributeRepository->reveal());

        $message = new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'size',
            'name' => 'Size',
            'type' => 'options',
            'description' => null,
            'options' => [
                ['type' => 'option', 'key' => 'small', 'name' => 'Small'],
                ['type' => 'option', 'key' => 'large', 'name' => 'Large'],
            ],
        ]);

        $result = ($handler)($message);

        $options = $result->getOptions();
        $this->assertCount(2, $options);
        $this->assertSame('small', $options[0]->getKey());
        $this->assertSame(0, $options[0]->getPosition());
        $this->assertSame('large', $options[1]->getKey());
        $this->assertSame(1, $options[1]->getPosition());
        $this->assertSame('Small', $options[0]->getTranslation('en')?->getName());
        $this->assertSame('Large', $options[1]->getTranslation('en')?->getName());
    }

    public function testCreateAttributeSkipsEmptyOptionKey(): void
    {
        $attribute = new Attribute();

        $this->attributeRepository->create()->willReturn($attribute);
        $this->attributeRepository->save($attribute)->shouldBeCalledOnce();

        $handler = new CreateAttributeMessageHandler($this->attributeRepository->reveal());

        $message = new CreateAttributeMessage([
            'locale' => 'en',
            'key' => 'size',
            'name' => 'Size',
            'type' => 'options',
            'description' => null,
            'options' => [
                ['type' => 'option', 'key' => '', 'name' => 'Empty'],
                ['type' => 'option', 'key' => 'large', 'name' => 'Large'],
            ],
        ]);

        $result = ($handler)($message);

        $options = $result->getOptions();
        $this->assertCount(1, $options);
        $this->assertSame('large', $options[0]->getKey());
    }
}
