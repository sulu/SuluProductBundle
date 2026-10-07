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
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Ai\AttributeFilter;
use Sulu\Product\Application\Ai\ProductUrlGenerator;
use Sulu\Product\Application\Ai\SearchProductsByAttributes;
use Sulu\Product\Application\Routing\VariantRouting;
use Sulu\Product\Application\Routing\VariantSlugResolver;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Tests\Unit\Application\Ai\Fixtures\FakeRouteGenerator;
use Sulu\Route\Domain\Model\Route;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Symfony\AI\Agent\Toolbox\ToolCallArgumentResolver;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Platform\Result\ToolCall;

#[CoversClass(SearchProductsByAttributes::class)]
class SearchProductsByAttributesTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    private SearchProductsByAttributes $searchProductsByAttributes;

    protected function setUp(): void
    {
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
        $this->searchProductsByAttributes = new SearchProductsByAttributes(
            $this->productRepository->reveal(),
            $this->attributeRepository->reveal(),
            new ProductUrlGenerator(new FakeRouteGenerator(), new VariantSlugResolver($this->createStub(RouteRepositoryInterface::class), VariantRouting::Route)),
        );
    }

    public function testInvokeWithEmptyFiltersReturnsNoMatchWithoutQuerying(): void
    {
        $this->attributeRepository->findOneBy(Argument::cetera())->shouldNotBeCalled();
        $this->productRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $result = ($this->searchProductsByAttributes)('en', []);

        $this->assertSame('no_match', $result['status']);
        $this->assertSame([], $result['results']);
        $this->assertNotNull($result['instruction']);
        $this->assertStringNotContainsString('sulu_', (string) $result['instruction']);
    }

    public function testInvokeWithBlankKeyReturnsUnknownAttributeWithoutLookup(): void
    {
        $this->attributeRepository->findOneBy(['key' => ''])->shouldNotBeCalled();

        $result = ($this->searchProductsByAttributes)('en', [new AttributeFilter('  ', 'red')]);

        $this->assertSame('unknown_attribute', $result['status']);
        $this->assertSame([], $result['results']);
    }

    public function testInvokeWithUnknownAttributeKeyReturnsUnknownAttribute(): void
    {
        $this->attributeRepository->findOneBy(['key' => 'missing'])->willReturn(null);
        $this->productRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $result = ($this->searchProductsByAttributes)('en', [new AttributeFilter('missing', 'red')]);

        $this->assertSame('unknown_attribute', $result['status']);
        $this->assertNotNull($result['instruction']);
        $this->assertStringNotContainsString('sulu_', (string) $result['instruction']);
    }

    public function testInvokeLooksUpAtMostFiveFilters(): void
    {
        $attribute = $this->attribute('a');
        $this->attributeRepository->findOneBy(Argument::any())->willReturn($attribute)->shouldBeCalledTimes(5);
        $this->productRepository->findBy(Argument::cetera())->willReturn([]);

        $filters = \array_map(
            static fn (int $i): AttributeFilter => new AttributeFilter('a' . $i, 'v' . $i),
            \range(1, 6),
        );

        ($this->searchProductsByAttributes)('en', $filters);
    }

    public function testInvokePassesAttributeValuesAndExcludesVariantsByDefault(): void
    {
        $attribute = $this->attribute('current');
        $this->attributeRepository->findOneBy(['key' => 'current'])->willReturn($attribute);

        $this->productRepository->findBy(
            Argument::that(static function(array $filters) use ($attribute): bool {
                return 'en' === $filters['locale']
                    && DimensionContentInterface::STAGE_LIVE === $filters['stage']
                    && [['attribute' => $attribute, 'value' => '16']] === $filters['attributeValues']
                    && [ProductInterface::TYPE_VARIANT] === $filters['excludeTypes']
                    && 10 === $filters['limit'];
            }),
            ['title' => 'asc'],
        )->willReturn([])->shouldBeCalled();

        $result = ($this->searchProductsByAttributes)('en', [new AttributeFilter('current', '16')]);

        $this->assertSame('no_match', $result['status']);
        $this->assertStringContainsString('Variants were left out', (string) $result['instruction']);
    }

    public function testInvokeIncludeVariantsTrueOmitsExcludeTypes(): void
    {
        $attribute = $this->attribute('current');
        $this->attributeRepository->findOneBy(['key' => 'current'])->willReturn($attribute);

        $this->productRepository->findBy(
            Argument::that(static fn (array $filters): bool => !isset($filters['excludeTypes'])),
            Argument::any(),
        )->willReturn([])->shouldBeCalled();

        $result = ($this->searchProductsByAttributes)('en', [new AttributeFilter('current', '16')], true);

        $this->assertStringNotContainsString('Variants were left out', (string) $result['instruction']);
    }

    public function testInvokeCapsLimitAtTwentyFive(): void
    {
        $attribute = $this->attribute('current');
        $this->attributeRepository->findOneBy(['key' => 'current'])->willReturn($attribute);

        $this->productRepository->findBy(
            Argument::that(static fn (array $filters): bool => 25 === $filters['limit']),
            Argument::any(),
        )->willReturn([])->shouldBeCalled();

        ($this->searchProductsByAttributes)('en', [new AttributeFilter('current', '16')], false, 999);
    }

    public function testInvokeReturnsMatchedProducts(): void
    {
        $attribute = $this->attribute('current');
        $this->attributeRepository->findOneBy(['key' => 'current'])->willReturn($attribute);

        $product = $this->buildMatchedProduct('en');
        $this->productRepository->findBy(Argument::cetera())->willReturn([$product]);

        $result = ($this->searchProductsByAttributes)('en', [new AttributeFilter('current', '16')]);

        $this->assertSame('ok', $result['status']);
        $this->assertNull($result['instruction']);
        $this->assertSame([
            'code' => 'ABC-1',
            'title' => 'Widget',
            'productFamily' => 'Fasteners',
            'url' => 'https://example.org/en/widget',
        ], $result['results'][0]);
    }

    public function testAgentCallDenormalizesFiltersIntoAttributeFilterObjects(): void
    {
        $tool = \iterator_to_array((new ReflectionToolFactory())->getTool(SearchProductsByAttributes::class))[0];

        $arguments = (new ToolCallArgumentResolver())->resolveArguments(
            $tool,
            new ToolCall('call-1', $tool->getName(), [
                'locale' => 'en',
                'filters' => [['key' => 'current', 'value' => '16']],
            ]),
        );

        $this->assertEquals([new AttributeFilter('current', '16')], $arguments['filters']);
    }

    public function testFiltersSchemaDescribesAnArrayOfKeyValueObjectsWithTheFullDescription(): void
    {
        $tool = \iterator_to_array((new ReflectionToolFactory())->getTool(SearchProductsByAttributes::class))[0];

        $parameters = $tool->getParameters();
        $this->assertNotNull($parameters);

        $this->assertJsonStringEqualsJsonString(
            (string) \json_encode([
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => ['key' => ['type' => 'string'], 'value' => ['type' => 'string']],
                    'required' => ['key', 'value'],
                    'additionalProperties' => false,
                ],
                'description' => 'one to five filters ANDed together, "key" is the exact attribute key from sulu_product_get_attributes and "value" a literal value, a substring for text or options, an exact number for a number attribute, never a comparison, range or wildcard like "16A or more"',
            ]),
            (string) \json_encode($parameters['properties']['filters']),
        );
    }

    private function attribute(string $key): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setKey($key);

        return $attribute;
    }

    private function buildMatchedProduct(string $locale): Product
    {
        $family = new ProductFamily();
        $family->addTranslation(new ProductFamilyTranslation($family, $locale, 'Fasteners'));

        $product = new Product();

        $unlocalized = $product->createDimensionContent();
        $unlocalized->setStage(DimensionContentInterface::STAGE_LIVE);
        $unlocalized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $unlocalized->setCode('ABC-1');
        $unlocalized->setProductFamily($family);
        $product->addDimensionContent($unlocalized);

        $localized = $product->createDimensionContent();
        $localized->setLocale($locale);
        $localized->setStage(DimensionContentInterface::STAGE_LIVE);
        $localized->setVersion(DimensionContentInterface::CURRENT_VERSION);
        $localized->setTitle('Widget');
        $localized->setRoute(new Route(ProductInterface::RESOURCE_KEY, $product->getUuid(), $locale, 'widget'));
        $product->addDimensionContent($localized);

        return $product;
    }
}
