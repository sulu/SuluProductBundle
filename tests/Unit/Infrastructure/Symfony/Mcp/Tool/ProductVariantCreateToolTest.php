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

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Mcp\Application\AdminLink\AdminLinkGeneratorInterface;
use Sulu\Product\Application\Mcp\VariantParentResolver;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductVariantCreateTool;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ProductVariantCreateTool::class)]
final class ProductVariantCreateToolTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<MessageBusInterface> */
    private ObjectProphecy $messageBus;
    /** @var ObjectProphecy<ContentManagerInterface> */
    private ObjectProphecy $contentManager;
    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;
    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;
    private ProductVariantCreateTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->contentManager = $this->prophesize(ContentManagerInterface::class);
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);

        $this->tool = new ProductVariantCreateTool(
            $this->messageBus->reveal(),
            $this->contentManager->reveal(),
            new VariantParentResolver(
                $this->productRepository->reveal(),
                $this->productFamilyRepository->reveal(),
            ),
            $this->prophesize(AdminLinkGeneratorInterface::class)->reveal(),
        );
    }

    public function testCreateVariantForcesTypeParentAndInheritedFamily(): void
    {
        $this->givenParentWithVariants('parent-uuid', $this->familyWithAttributes(['family-uuid' => ['shared-uuid' => false, 'axis-uuid' => true]]));
        $captured = $this->captureDispatchedMessage(new Product('variant-uuid'));

        $result = $this->tool->createProductVariant('en', 'parent-uuid', 'Red / XL');

        $this->assertTrue($result['success']);
        $this->assertSame('variant-uuid', $result['uuid']);
        $this->assertSame('parent-uuid', $result['parent']);

        $message = $captured();
        $this->assertInstanceOf(CreateProductMessage::class, $message);
        $data = $message->getData();
        $this->assertSame(ProductInterface::TYPE_VARIANT, $data['type'] ?? null);
        $this->assertSame('parent-uuid', $data['parent'] ?? null);
        $this->assertSame('family-uuid', $data['productFamily']);
    }

    public function testCreateVariantKeepsOnlyVariantSpecificAttributes(): void
    {
        $this->givenParentWithVariants('parent-uuid', $this->familyWithAttributes(['family-uuid' => ['shared-uuid' => false, 'axis-uuid' => true]]));
        $captured = $this->captureDispatchedMessage(new Product('variant-uuid'));

        $this->tool->createProductVariant('en', 'parent-uuid', 'Red / XL', attributes: [
            'shared-uuid' => 'shared, belongs on the parent',
            'axis-uuid' => 'red',
        ]);

        $message = $captured();
        $this->assertInstanceOf(CreateProductMessage::class, $message);
        $this->assertSame(['axis-uuid' => 'red'], $message->getData()['attributes'] ?? null);
    }

    public function testCreateVariantRejectsAPlainProductAsParent(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT);
        $this->productRepository->getOneBy(['uuid' => 'parent-uuid'])->willReturn($parent);

        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->tool->createProductVariant('en', 'parent-uuid', 'Red / XL');

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('cannot have variants', $result['error']);
        $this->assertNotEmpty($result['hint']);
    }

    public function testCreateVariantRejectsAVariantAsParent(): void
    {
        $parent = new Product('variant-uuid');
        $parent->setType(ProductInterface::TYPE_VARIANT);
        $this->productRepository->getOneBy(['uuid' => 'variant-uuid'])->willReturn($parent);

        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->tool->createProductVariant('en', 'variant-uuid', 'Nested');

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('cannot have variants', $result['error']);
    }

    public function testCreateVariantRejectsAParentWithoutFamily(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $this->productRepository->getOneBy(['uuid' => 'parent-uuid'])->willReturn($parent);
        $this->productFamilyRepository->findOneBy(Argument::cetera())->willReturn(null);

        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->tool->createProductVariant('en', 'parent-uuid', 'Red / XL');

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('no product family', $result['error']);
    }

    public function testCreateVariantReturnsErrorOnFailure(): void
    {
        $this->givenParentWithVariants('parent-uuid', $this->familyWithAttributes(['family-uuid' => []]));
        $this->messageBus->dispatch(Argument::cetera())
            ->willThrow(new \RuntimeException('Attribute "size" is required'));

        $result = $this->tool->createProductVariant('en', 'parent-uuid', 'Red / XL');

        $this->assertArrayHasKey('error', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('is required', $result['error']);
        $this->assertNotEmpty($result['hint']);
    }

    public function testCreateProductVariantMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ProductVariantCreateTool::class, 'createProductVariant');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'createProductVariant() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_product_variant_create', $instance->name);
    }

    private function givenParentWithVariants(string $parentUuid, ProductFamilyInterface $family): void
    {
        $parent = new Product($parentUuid);
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->productRepository->getOneBy(['uuid' => $parentUuid])->willReturn($parent);
        $this->productFamilyRepository->findOneBy(['productUuid' => $parentUuid])->willReturn($family);

        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn([]);
    }

    /**
     * @return \Closure(): ?object
     */
    private function captureDispatchedMessage(ProductInterface $result): \Closure
    {
        $captured = null;

        $this->messageBus->dispatch(Argument::type(Envelope::class), Argument::cetera())
            ->will(function(array $args) use ($result, &$captured): Envelope {
                /** @var Envelope $envelope */
                $envelope = $args[0];
                $captured = $envelope->getMessage();

                return $envelope->with(new HandledStamp($result, 'handler'));
            });

        return static function() use (&$captured): ?object {
            return $captured;
        };
    }

    /**
     * @param array<string, array<string, bool>> $attributesByFamilyUuid
     */
    private function familyWithAttributes(array $attributesByFamilyUuid): ProductFamilyInterface
    {
        $uuid = (string) \array_key_first($attributesByFamilyUuid);
        $family = new ProductFamily($uuid);

        $group = new AttributeGroup();
        foreach ($attributesByFamilyUuid[$uuid] as $attributeUuid => $variantSpecific) {
            $attribute = new Attribute($group, $attributeUuid);

            $familyAttribute = new ProductFamilyAttribute($family, $attribute);
            $familyAttribute->setVariantSpecific($variantSpecific);
            $family->addFamilyAttribute($familyAttribute);
        }

        return $family;
    }

    public function testCreateVariantSendsCodeStatusAndDetails(): void
    {
        $this->givenParentWithVariants('parent-uuid', $this->familyWithAttributes(['family-uuid' => ['shared-uuid' => false]]));
        $captured = $this->captureDispatchedMessage(new Product('variant-uuid'));

        $this->tool->createProductVariant('en', 'parent-uuid', 'Red', code: 'RED-1', status: 'available', details: ['shortDescription' => 'Red']);

        $message = $captured();
        $this->assertInstanceOf(CreateProductMessage::class, $message);
        $data = $message->getData();
        $this->assertSame('RED-1', $data['code'] ?? null);
        $this->assertSame('available', $data['status'] ?? null);
        $this->assertSame(['shortDescription' => 'Red'], $data['details'] ?? null);
    }

    public function testCreateVariantReturnsTheAdminUrl(): void
    {
        $adminLinkGenerator = $this->prophesize(AdminLinkGeneratorInterface::class);
        $adminLinkGenerator->generate('product_variant', ['locale' => 'en', 'uuid' => 'parent-uuid'])->willReturn('https://admin.example/variant');

        $tool = new ProductVariantCreateTool(
            $this->messageBus->reveal(),
            $this->contentManager->reveal(),
            new VariantParentResolver($this->productRepository->reveal(), $this->productFamilyRepository->reveal()),
            $adminLinkGenerator->reveal(),
        );

        $this->givenParentWithVariants('parent-uuid', $this->familyWithAttributes(['family-uuid' => ['shared-uuid' => false]]));
        $this->captureDispatchedMessage(new Product('variant-uuid'));

        $result = $tool->createProductVariant('en', 'parent-uuid', 'Red');

        $this->assertSame('https://admin.example/variant', $result['admin_url'] ?? null);
    }
}
