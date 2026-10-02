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
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductVariantUpdateTool;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(ProductVariantUpdateTool::class)]
final class ProductVariantUpdateToolTest extends TestCase
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
    private ProductVariantUpdateTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->contentManager = $this->prophesize(ContentManagerInterface::class);
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);

        $this->tool = new ProductVariantUpdateTool(
            $this->messageBus->reveal(),
            $this->contentManager->reveal(),
            $this->productRepository->reveal(),
            new VariantParentResolver(
                $this->productRepository->reveal(),
                $this->productFamilyRepository->reveal(),
            ),
            $this->prophesize(AdminLinkGeneratorInterface::class)->reveal(),
        );
    }

    public function testUpdateVariantMergesAxesAndStripsSharedAttributes(): void
    {
        $captured = $this->givenVariantOfParent(['attributes' => ['shared-uuid' => 'shared', 'axis-uuid' => 'M']]);

        $result = $this->tool->updateProductVariant('en', 'parent-uuid', 'variant-uuid', attributes: ['axis-uuid' => 'L']);

        $this->assertTrue($result['success']);
        $this->assertSame('parent-uuid', $result['parent']);

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        $this->assertSame(
            ['axis-uuid' => 'L'],
            $message->getData()['attributes'] ?? null,
            'The shared attribute belongs on the parent; only the variant axis may be written here.',
        );
    }

    public function testUpdateVariantKeepsTheInheritedFamily(): void
    {
        $captured = $this->givenVariantOfParent(['productFamily' => 'stale-family']);

        $this->tool->updateProductVariant('en', 'parent-uuid', 'variant-uuid', title: 'Red / L');

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        $this->assertSame('family-uuid', $message->getData()['productFamily'] ?? null);
    }

    public function testUpdateVariantNeverSendsTypeOrParent(): void
    {
        $captured = $this->givenVariantOfParent(['type' => 'variant', 'parent' => 'parent-uuid']);

        $this->tool->updateProductVariant('en', 'parent-uuid', 'variant-uuid', title: 'Red / L');

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        $this->assertArrayNotHasKey('type', $message->getData());
        $this->assertArrayNotHasKey('parent', $message->getData());
    }

    public function testUpdateVariantRejectsAVariantOfAnotherParent(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $foreignParent = new Product('other-parent');
        $variant = new Product('variant-uuid');
        $variant->setParent($foreignParent);

        $this->productRepository->getOneBy(['uuid' => 'parent-uuid'])->willReturn($parent);
        $this->productRepository->getOneBy(['uuid' => 'variant-uuid'])->willReturn($variant);
        $this->productFamilyRepository->findOneBy(Argument::cetera())->willReturn($this->family());

        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->tool->updateProductVariant('en', 'parent-uuid', 'variant-uuid', title: 'x');

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('does not belong to parent', $result['error']);
    }

    public function testUpdateVariantRejectsAParentThatCannotHaveVariants(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT);
        $this->productRepository->getOneBy(['uuid' => 'parent-uuid'])->willReturn($parent);

        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->tool->updateProductVariant('en', 'parent-uuid', 'variant-uuid', title: 'x');

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('cannot have variants', $result['error']);
    }

    public function testUpdateProductVariantMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ProductVariantUpdateTool::class, 'updateProductVariant');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'updateProductVariant() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_product_variant_update', $instance->name);
    }

    /**
     * @param array<string, mixed> $currentData
     *
     * @return \Closure(): ?object
     */
    private function givenVariantOfParent(array $currentData): \Closure
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);

        $this->productRepository->getOneBy(['uuid' => 'parent-uuid'])->willReturn($parent);
        $this->productRepository->getOneBy(['uuid' => 'variant-uuid'])->willReturn($variant);
        $this->productRepository->getOneBy(Argument::type('array'), Argument::type('array'))->willReturn($variant);
        $this->productFamilyRepository->findOneBy(['productUuid' => 'parent-uuid'])->willReturn($this->family());

        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn($currentData);

        $captured = null;
        $this->messageBus->dispatch(Argument::type(Envelope::class), Argument::cetera())
            ->will(function(array $args) use ($variant, &$captured): Envelope {
                /** @var Envelope $envelope */
                $envelope = $args[0];
                $captured = $envelope->getMessage();

                return $envelope->with(new HandledStamp($variant, 'handler'));
            });

        return static function() use (&$captured): ?object {
            return $captured;
        };
    }

    private function family(): ProductFamily
    {
        $family = new ProductFamily('family-uuid');

        $group = new AttributeGroup();
        foreach (['shared-uuid' => false, 'axis-uuid' => true] as $attributeUuid => $variantSpecific) {
            $attribute = new Attribute($group, $attributeUuid);

            $familyAttribute = new ProductFamilyAttribute($family, $attribute);
            $familyAttribute->setVariantSpecific($variantSpecific);
            $family->addFamilyAttribute($familyAttribute);
        }

        return $family;
    }

    public function testUpdateVariantSendsCodeStatusAndMergedDetails(): void
    {
        $captured = $this->givenVariantOfParent(['details' => ['shortDescription' => 'Old', 'keep' => 'yes']]);

        $this->tool->updateProductVariant('en', 'parent-uuid', 'variant-uuid', code: 'RED-2', status: 'available', details: ['shortDescription' => 'New']);

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        $data = $message->getData();
        $this->assertSame('RED-2', $data['code'] ?? null);
        $this->assertSame('available', $data['status'] ?? null);
        $this->assertSame(['shortDescription' => 'New', 'keep' => 'yes'], $data['details'] ?? null);
    }

    public function testUpdateVariantReturnsTheAdminUrl(): void
    {
        $adminLinkGenerator = $this->prophesize(AdminLinkGeneratorInterface::class);
        $adminLinkGenerator->generate('product_variant', ['locale' => 'en', 'uuid' => 'parent-uuid'])->willReturn('https://admin.example/variant');

        $tool = new ProductVariantUpdateTool(
            $this->messageBus->reveal(),
            $this->contentManager->reveal(),
            $this->productRepository->reveal(),
            new VariantParentResolver($this->productRepository->reveal(), $this->productFamilyRepository->reveal()),
            $adminLinkGenerator->reveal(),
        );
        $this->givenVariantOfParent([]);

        $result = $tool->updateProductVariant('en', 'parent-uuid', 'variant-uuid', title: 'x');

        $this->assertSame('https://admin.example/variant', $result['admin_url'] ?? null);
    }

    public function testUpdateVariantReturnsErrorOnFailure(): void
    {
        $this->givenVariantOfParent([]);
        $this->productRepository->getOneBy(Argument::type('array'), Argument::type('array'))->willThrow(new \RuntimeException('Product code "RED-2" is already in use'));

        $result = $this->tool->updateProductVariant('en', 'parent-uuid', 'variant-uuid', code: 'RED-2');

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('already in use', $result['error']);
        $this->assertIsString($result['hint']);
        $this->assertNotEmpty($result['hint']);
    }
}
