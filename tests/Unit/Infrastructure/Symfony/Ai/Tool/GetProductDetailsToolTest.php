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
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\TextAttributeType;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Ai\Tool\GetProductDetailsTool;
use Sulu\Route\Domain\Model\Route;

#[CoversClass(GetProductDetailsTool::class)]
class GetProductDetailsToolTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    private GetProductDetailsTool $tool;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->tool = new GetProductDetailsTool(
            $this->productRepository->reveal(),
            new AttributeTypeRegistry([new TextAttributeType()]),
        );
    }

    public function testInvokeThrowsWhenProductNotFound(): void
    {
        $this->productRepository->getOneBy([
            'code' => 'MISSING',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willThrow(new ProductNotFoundException(['code' => 'MISSING']));

        $this->expectException(\InvalidArgumentException::class);

        ($this->tool)('MISSING', 'en');
    }

    public function testInvokeThrowsWhenLiveContentIncomplete(): void
    {
        $product = new Product();
        $unlocalized = $product->createDimensionContent();
        $unlocalized->setStage(DimensionContentInterface::STAGE_LIVE);
        $unlocalized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $unlocalized->setCode('ABC-1');
        $product->addDimensionContent($unlocalized);
        // no localized content added on purpose

        $this->productRepository->getOneBy([
            'code' => 'ABC-1',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn($product);

        $this->expectException(\InvalidArgumentException::class);

        ($this->tool)('ABC-1', 'en');
    }

    public function testInvokeReturnsSpecGroupsOrderedByFirstAppearance(): void
    {
        [$product, $localized, $unlocalized] = $this->buildProduct('en');

        $groupA = new AttributeGroup();
        $groupA->addTranslation(new AttributeGroupTranslation($groupA, 'en', 'Electrical'));
        $groupB = new AttributeGroup();
        $groupB->addTranslation(new AttributeGroupTranslation($groupB, 'en', 'Mechanical'));

        $current = new Attribute($groupA);
        $current->setKey('current');
        $current->setType(AttributeInterface::TYPE_TEXT);
        $current->addTranslation(new AttributeTranslation($current, 'en', 'Current'));

        $weight = new Attribute($groupB);
        $weight->setKey('weight');
        $weight->setType(AttributeInterface::TYPE_TEXT);
        $weight->addTranslation(new AttributeTranslation($weight, 'en', 'Weight'));

        $voltage = new Attribute($groupA);
        $voltage->setKey('voltage');
        $voltage->setType(AttributeInterface::TYPE_TEXT);
        $voltage->addTranslation(new AttributeTranslation($voltage, 'en', 'Voltage'));

        $unlocalized->addAttribute($this->value($unlocalized, $current, '16A'));
        $localized->addAttribute($this->value($unlocalized, $weight, '2kg'));
        $unlocalized->addAttribute($this->value($unlocalized, $voltage, '230V'));

        $this->productRepository->getOneBy([
            'code' => 'ABC-1',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn($product);

        $result = ($this->tool)('ABC-1', 'en');

        $this->assertSame('ABC-1', $result['code']);
        $this->assertSame('Widget', $result['title']);
        $this->assertSame([
            ['label' => 'Electrical', 'attributes' => [
                ['label' => 'Current', 'value' => '16A'],
                ['label' => 'Voltage', 'value' => '230V'],
            ]],
            ['label' => 'Mechanical', 'attributes' => [
                ['label' => 'Weight', 'value' => '2kg'],
            ]],
        ], $result['specGroups']);
    }

    public function testInvokeSkipsAttributeValuesThatDisplayAsNull(): void
    {
        [$product, , $unlocalized] = $this->buildProduct('en');

        $group = new AttributeGroup();
        $group->addTranslation(new AttributeGroupTranslation($group, 'en', 'General'));

        $empty = new Attribute($group);
        $empty->setKey('empty');
        $empty->setType(AttributeInterface::TYPE_TEXT);

        $unlocalized->addAttribute($this->value($unlocalized, $empty, null));

        $this->productRepository->getOneBy([
            'code' => 'ABC-1',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn($product);

        $result = ($this->tool)('ABC-1', 'en');

        $this->assertSame([], $result['specGroups']);
    }

    public function testInvokeFallsBackToAttributeKeyAndGroupUuidWhenTranslationsMissing(): void
    {
        [$product, , $unlocalized] = $this->buildProduct('en');

        $group = new AttributeGroup();
        $group->setUuid('group-uuid');

        $attribute = new Attribute($group);
        $attribute->setKey('material');
        $attribute->setType(AttributeInterface::TYPE_TEXT);

        $unlocalized->addAttribute($this->value($unlocalized, $attribute, 'Brass'));

        $this->productRepository->getOneBy([
            'code' => 'ABC-1',
            'locale' => 'en',
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ])->willReturn($product);

        $result = ($this->tool)('ABC-1', 'en');

        $this->assertSame('group-uuid', $result['specGroups'][0]['label']);
        $this->assertSame('material', $result['specGroups'][0]['attributes'][0]['label']);
    }

    /**
     * @return array{0: Product, 1: ProductDimensionContentInterface, 2: ProductDimensionContentInterface}
     */
    private function buildProduct(string $locale): array
    {
        $product = new Product();

        $unlocalized = $product->createDimensionContent();
        $unlocalized->setStage(DimensionContentInterface::STAGE_LIVE);
        $unlocalized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $unlocalized->setCode('ABC-1');
        $product->addDimensionContent($unlocalized);

        $localized = $product->createDimensionContent();
        $localized->setLocale($locale);
        $localized->setStage(DimensionContentInterface::STAGE_LIVE);
        $localized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $localized->setTitle('Widget');
        $localized->setRoute(new Route(ProductInterface::RESOURCE_KEY, $product->getUuid(), $locale, 'widget'));
        $product->addDimensionContent($localized);

        return [$product, $localized, $unlocalized];
    }

    private function value(ProductDimensionContentInterface $dimensionContent, Attribute $attribute, ?string $text): ProductAttributeValue
    {
        $value = new ProductAttributeValue($dimensionContent, $attribute, $attribute->getKey());
        $value->setText($text);

        return $value;
    }
}
