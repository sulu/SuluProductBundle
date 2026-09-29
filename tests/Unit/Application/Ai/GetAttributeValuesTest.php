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

namespace Sulu\Product\Tests\Unit\Application\Ai;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Ai\GetAttributeValues;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\DateAttributeType;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Application\AttributeType\OptionsAttributeType;
use Sulu\Product\Application\AttributeType\TextAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductAttributeValueRepositoryInterface;
use Sulu\Product\Tests\Unit\Application\Ai\Fixtures\BooleanAttributeTypeFake;

#[CoversClass(GetAttributeValues::class)]
class GetAttributeValuesTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    /** @var ObjectProphecy<ProductAttributeValueRepositoryInterface> */
    private ObjectProphecy $productAttributeValueRepository;

    private GetAttributeValues $getAttributeValues;

    protected function setUp(): void
    {
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $this->productAttributeValueRepository = $this->prophesize(ProductAttributeValueRepositoryInterface::class);

        $registry = new AttributeTypeRegistry([
            new TextAttributeType(),
            new NumberAttributeType(),
            new OptionsAttributeType(),
            new DateAttributeType(),
        ]);

        $this->getAttributeValues = new GetAttributeValues(
            $this->attributeRepository->reveal(),
            $this->productAttributeValueRepository->reveal(),
            $registry,
        );
    }

    private function dimensionContent(): ProductDimensionContentInterface
    {
        $product = new Product();

        return $product->createDimensionContent();
    }

    public function testInvokeWithBlankKeyReturnsUnknownAttributeWithoutLookup(): void
    {
        $this->attributeRepository->findOneBy(['key' => ''])->shouldNotBeCalled();

        $result = ($this->getAttributeValues)('  ', 'en');

        $this->assertSame('unknown_attribute', $result['status']);
        $this->assertSame([], $result['values']);
        $this->assertNotNull($result['instruction']);
    }

    public function testInvokeWithUnknownKeyReturnsUnknownAttribute(): void
    {
        $this->attributeRepository->findOneBy(['key' => 'missing'])->willReturn(null);
        $this->productAttributeValueRepository->findBy()->shouldNotBeCalled();

        $result = ($this->getAttributeValues)('missing', 'en');

        $this->assertSame('unknown_attribute', $result['status']);
    }

    public function testInvokeCountsTextValuesMostCommonFirst(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('material');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $this->attributeRepository->findOneBy(['key' => 'material'])->willReturn($attribute);

        $values = [
            $this->textValue($attribute, 'Brass'),
            $this->textValue($attribute, 'Steel'),
            $this->textValue($attribute, 'Brass'),
        ];
        $this->productAttributeValueRepository->findBy([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn($values);

        $result = ($this->getAttributeValues)('material', 'en');

        $this->assertSame('ok', $result['status']);
        $this->assertSame([
            ['value' => 'Brass', 'count' => 2],
            ['value' => 'Steel', 'count' => 1],
        ], $result['values']);
    }

    public function testInvokeSkipsNullTextValues(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('material');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $this->attributeRepository->findOneBy(['key' => 'material'])->willReturn($attribute);

        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, 'material');
        $value->setText(null);

        $this->productAttributeValueRepository->findBy([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn([$value]);

        $result = ($this->getAttributeValues)('material', 'en');

        $this->assertSame([], $result['values']);
    }

    public function testInvokeFormatsIntegerValuedNumberWithoutDecimals(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('current');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $this->attributeRepository->findOneBy(['key' => 'current'])->willReturn($attribute);

        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, 'current');
        $value->setNumber(16.0);

        $this->productAttributeValueRepository->findBy([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn([$value]);

        $result = ($this->getAttributeValues)('current', 'en');

        $this->assertSame([['value' => '16', 'count' => 1]], $result['values']);
    }

    public function testInvokeFormatsFractionalNumber(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('current');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $this->attributeRepository->findOneBy(['key' => 'current'])->willReturn($attribute);

        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, 'current');
        $value->setNumber(16.5);

        $this->productAttributeValueRepository->findBy([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn([$value]);

        $result = ($this->getAttributeValues)('current', 'en');

        $this->assertSame([['value' => '16.5', 'count' => 1]], $result['values']);
    }

    public function testInvokeFormatsDateValue(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('released');
        $attribute->setType(AttributeInterface::TYPE_DATE);
        $this->attributeRepository->findOneBy(['key' => 'released'])->willReturn($attribute);

        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, 'released');
        $value->setNumber((float) (new \DateTimeImmutable('2024-05-01', new \DateTimeZone('UTC')))->getTimestamp());

        $this->productAttributeValueRepository->findBy([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn([$value]);

        $result = ($this->getAttributeValues)('released', 'en');

        $this->assertSame([['value' => '2024-05-01', 'count' => 1]], $result['values']);
    }

    public function testInvokeUsesTranslatedOptionLabel(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $this->attributeRepository->findOneBy(['key' => 'color'])->willReturn($attribute);

        $option = new AttributeOption($attribute, 'red');
        $option->addTranslation(new AttributeOptionTranslation($option, 'en', 'Red'));

        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, 'color');
        $value->setAttributeOption($option);

        $this->productAttributeValueRepository->findBy([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn([$value]);

        $result = ($this->getAttributeValues)('color', 'en');

        $this->assertSame([['value' => 'Red', 'count' => 1]], $result['values']);
    }

    public function testInvokeFallsBackToOptionKeyWhenOptionTranslationMissing(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $this->attributeRepository->findOneBy(['key' => 'color'])->willReturn($attribute);

        $option = new AttributeOption($attribute, 'blue');

        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, 'color');
        $value->setAttributeOption($option);

        $this->productAttributeValueRepository->findBy([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn([$value]);

        $result = ($this->getAttributeValues)('color', 'en');

        $this->assertSame([['value' => 'blue', 'count' => 1]], $result['values']);
    }

    public function testInvokeSkipsOptionValueWithoutAnOption(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $this->attributeRepository->findOneBy(['key' => 'color'])->willReturn($attribute);

        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, 'color');

        $this->productAttributeValueRepository->findBy([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn([$value]);

        $result = ($this->getAttributeValues)('color', 'en');

        $this->assertSame([], $result['values']);
    }

    public function testInvokeSkipsValueOfUnsupportedCustomType(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('flag');
        $attribute->setType('custom_boolean');
        $this->attributeRepository->findOneBy(['key' => 'flag'])->willReturn($attribute);

        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, 'flag');

        $registry = new AttributeTypeRegistry([new BooleanAttributeTypeFake()]);
        $getAttributeValues = new GetAttributeValues(
            $this->attributeRepository->reveal(),
            $this->productAttributeValueRepository->reveal(),
            $registry,
        );

        $this->productAttributeValueRepository->findBy([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn([$value]);

        $result = ($getAttributeValues)('flag', 'en');

        $this->assertSame([], $result['values']);
    }

    public function testInvokeAppliesLimit(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('material');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $this->attributeRepository->findOneBy(['key' => 'material'])->willReturn($attribute);

        $values = [
            $this->textValue($attribute, 'Brass'),
            $this->textValue($attribute, 'Brass'),
            $this->textValue($attribute, 'Steel'),
        ];
        $this->productAttributeValueRepository->findBy([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ])->willReturn($values);

        $result = ($this->getAttributeValues)('material', 'en', 1);

        $this->assertCount(1, $result['values']);
        $this->assertSame('Brass', $result['values'][0]['value']);
    }

    private function textValue(Attribute $attribute, string $text): ProductAttributeValue
    {
        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, $attribute->getKey());
        $value->setText($text);

        return $value;
    }
}
