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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Ai\GetAttributeValues;
use Sulu\Product\Application\Attribute\ProductAttributeValueFormatter;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
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

        $this->getAttributeValues = new GetAttributeValues(
            $this->attributeRepository->reveal(),
            $this->productAttributeValueRepository->reveal(),
            new ProductAttributeValueFormatter(new MeasurementRegistry()),
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
        $this->assertStringNotContainsString('sulu_', (string) $result['instruction']);
    }

    public function testInvokeWithUnknownKeyReturnsUnknownAttribute(): void
    {
        $this->attributeRepository->findOneBy(['key' => 'missing'])->willReturn(null);
        $this->productAttributeValueRepository->countValues(Argument::cetera())->shouldNotBeCalled();

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

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 15)->willReturn([
            ['value' => $this->textValue($attribute, 'Brass'), 'count' => 2],
            ['value' => $this->textValue($attribute, 'Steel'), 'count' => 1],
        ]);

        $result = ($this->getAttributeValues)('material', 'en');

        $this->assertSame('ok', $result['status']);
        $this->assertSame([
            ['value' => 'Brass', 'searchValue' => 'Brass', 'count' => 2],
            ['value' => 'Steel', 'searchValue' => 'Steel', 'count' => 1],
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

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 15)->willReturn([['value' => $value, 'count' => 1]]);

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

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 15)->willReturn([['value' => $value, 'count' => 1]]);

        $result = ($this->getAttributeValues)('current', 'en');

        $this->assertSame([['value' => '16', 'searchValue' => '16', 'count' => 1]], $result['values']);
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

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 15)->willReturn([['value' => $value, 'count' => 1]]);

        $result = ($this->getAttributeValues)('current', 'en');

        $this->assertSame([['value' => '16.5', 'searchValue' => '16.5', 'count' => 1]], $result['values']);
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

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 15)->willReturn([['value' => $value, 'count' => 1]]);

        $result = ($this->getAttributeValues)('released', 'en');

        $this->assertSame([['value' => 'May 1, 2024', 'searchValue' => '1714521600', 'count' => 1]], $result['values']);
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

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 15)->willReturn([['value' => $value, 'count' => 1]]);

        $result = ($this->getAttributeValues)('color', 'en');

        $this->assertSame([['value' => 'Red', 'searchValue' => 'red', 'count' => 1]], $result['values']);
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

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 15)->willReturn([['value' => $value, 'count' => 1]]);

        $result = ($this->getAttributeValues)('color', 'en');

        $this->assertSame([['value' => 'blue', 'searchValue' => 'blue', 'count' => 1]], $result['values']);
    }

    public function testInvokeSkipsOptionValueWithoutAnOption(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('color');
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $this->attributeRepository->findOneBy(['key' => 'color'])->willReturn($attribute);

        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, 'color');

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 15)->willReturn([['value' => $value, 'count' => 1]]);

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

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 15)->willReturn([['value' => $value, 'count' => 1]]);

        $result = ($this->getAttributeValues)('flag', 'en');

        $this->assertSame([], $result['values']);
    }

    public function testInvokeAppliesLimit(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('material');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $this->attributeRepository->findOneBy(['key' => 'material'])->willReturn($attribute);

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 1)->willReturn([
            ['value' => $this->textValue($attribute, 'Brass'), 'count' => 2],
        ]);

        $result = ($this->getAttributeValues)('material', 'en', 1);

        $this->assertCount(1, $result['values']);
        $this->assertSame('Brass', $result['values'][0]['value']);
    }

    public function testInvokeReturnsTheOptionKeyAsSearchValueNextToTheTranslatedLabel(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('finish');
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $this->attributeRepository->findOneBy(['key' => 'finish'])->willReturn($attribute);

        $option = new AttributeOption($attribute, 'heavy_duty');
        $option->addTranslation(new AttributeOptionTranslation($option, 'en', 'Heavy duty'));

        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, 'finish');
        $value->setAttributeOption($option);

        $this->productAttributeValueRepository->countValues(Argument::cetera())->willReturn([['value' => $value, 'count' => 1]]);

        $result = ($this->getAttributeValues)('finish', 'en');

        $this->assertSame([['value' => 'Heavy duty', 'searchValue' => 'heavy_duty', 'count' => 1]], $result['values']);
    }

    public function testInvokeAppliesTheAttributesDisplayFormatAndUnit(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('current');
        $attribute->setType(AttributeInterface::TYPE_NUMBER);
        $attribute->setConfig(['displayFormat' => '%value% %unit%', 'unit' => 'MILLIMETER']);
        $this->attributeRepository->findOneBy(['key' => 'current'])->willReturn($attribute);

        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, 'current');
        $value->setNumber(16.0);

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 15)->willReturn([['value' => $value, 'count' => 3]]);

        $result = ($this->getAttributeValues)('current', 'en');

        $this->assertSame([['value' => '16 mm', 'searchValue' => '16', 'count' => 3]], $result['values']);
    }

    public function testInvokeMergesGroupsThatDisplayTheSame(): void
    {
        $group = new AttributeGroup();
        $attribute = new Attribute($group);
        $attribute->setKey('material');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $this->attributeRepository->findOneBy(['key' => 'material'])->willReturn($attribute);

        $this->productAttributeValueRepository->countValues([
            'attribute' => $attribute,
            'locale' => 'en',
            'stage' => 'live',
        ], 15)->willReturn([
            ['value' => $this->textValue($attribute, 'Brass'), 'count' => 2],
            ['value' => $this->textValue($attribute, 'Brass '), 'count' => 1],
        ]);

        $result = ($this->getAttributeValues)('material', 'en');

        $this->assertSame([['value' => 'Brass', 'searchValue' => 'Brass', 'count' => 3]], $result['values']);
    }

    private function textValue(Attribute $attribute, string $text): ProductAttributeValue
    {
        $value = new ProductAttributeValue($this->dimensionContent(), $attribute, $attribute->getKey());
        $value->setText($text);

        return $value;
    }
}
