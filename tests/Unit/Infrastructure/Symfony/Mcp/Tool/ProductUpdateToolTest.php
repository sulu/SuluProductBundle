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
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Mcp\Application\AdminLink\AdminLinkGeneratorInterface;
use Sulu\Mcp\Application\Content\BlockDataValidator;
use Sulu\Mcp\Application\Content\ContentMetadataMapper;
use Sulu\Mcp\Application\Metadata\MetadataLocaleResolver;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductUpdateTool;
use Sulu\Product\Tests\Unit\Fixture\ArrayMetadataProvider;
use Sulu\Product\Tests\Unit\Fixture\FixedBlockIdGenerator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

#[CoversClass(ProductUpdateTool::class)]
final class ProductUpdateToolTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<MessageBusInterface> */
    private ObjectProphecy $messageBus;
    /** @var ObjectProphecy<ContentManagerInterface> */
    private ObjectProphecy $contentManager;
    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;
    private ProductUpdateTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->contentManager = $this->prophesize(ContentManagerInterface::class);
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);

        $this->tool = new ProductUpdateTool(
            $this->messageBus->reveal(),
            $this->contentManager->reveal(),
            $this->productRepository->reveal(),
            new ContentMetadataMapper(new ArrayMetadataProvider()),
            new BlockDataValidator($this->formMetadataProvider(), new MetadataLocaleResolver(new TokenStorage(), 'en')),
            FixedBlockIdGenerator::returning('b1', 'b2', 'b3'),
            $this->prophesize(AdminLinkGeneratorInterface::class)->reveal(),
        );
    }

    public function testUpdateProductMergesAttributesIntoTheCurrentState(): void
    {
        $captured = $this->givenProduct(['title' => 'Shirt', 'attributes' => ['10' => 'blue', '11' => 'M']]);

        $result = $this->tool->updateProduct('uuid-1', 'en', attributes: ['11' => 'L']);

        $this->assertTrue($result['success']);

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        $this->assertSame(['10' => 'blue', '11' => 'L'], $message->getData()['attributes'] ?? null);
    }

    public function testUpdateProductSetsTheShadow(): void
    {
        $captured = $this->givenProduct(['title' => 'Shirt']);

        $this->tool->updateProduct('uuid-1', 'en', shadowOn: true, shadowLocale: 'de');

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertTrue($data['shadowOn']);
        $this->assertSame('de', $data['shadowLocale']);
    }

    public function testUpdateProductRejectsShadowingALocaleThatIsAlreadyMirrored(): void
    {
        $this->givenProduct(['title' => 'Shirt', 'shadowLocales' => ['fr' => 'en']]);

        $result = $this->tool->updateProduct('uuid-1', 'en', shadowOn: true, shadowLocale: 'de');

        $this->assertArrayHasKey('error', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('mirrored', $result['error']);
    }

    public function testUpdateProductRejectsALocaleShadowingItselfWhenShadowOnIsOmitted(): void
    {
        $this->givenProduct(['title' => 'Shirt']);

        $result = $this->tool->updateProduct('uuid-1', 'en', shadowLocale: 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('itself', $result['error']);
    }

    public function testUpdateProductOnlyChangesWhatWasPassed(): void
    {
        $captured = $this->givenProduct(['title' => 'Shirt', 'code' => 'SHIRT-1']);

        $this->tool->updateProduct('uuid-1', 'en', title: 'Shirt v2');

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        $data = $message->getData();
        $this->assertSame('Shirt v2', $data['title'] ?? null);
        $this->assertSame('SHIRT-1', $data['code'] ?? null);
    }

    public function testUpdateProductNeverSendsTypeOrParent(): void
    {
        $captured = $this->givenProduct(['title' => 'Shirt', 'type' => 'product', 'parent' => 'some-uuid']);

        $this->tool->updateProduct('uuid-1', 'en', title: 'Shirt v2');

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        $this->assertArrayNotHasKey('type', $message->getData(), 'type is identity-level: only the variant tools may set it.');
        $this->assertArrayNotHasKey('parent', $message->getData(), 'parent is identity-level: only the variant tools may set it.');
    }

    public function testUpdateProductRefusesAVariant(): void
    {
        $parent = new Product('parent-uuid');
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($parent);

        $this->productRepository->getOneBy(Argument::cetera())->willReturn($variant);
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->tool->updateProduct('variant-uuid', 'en', productFamily: 'other-family');

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('variant', $result['error']);
        $this->assertIsString($result['hint']);
        $this->assertStringContainsString('sulu_product_variant_update', $result['hint']);
    }

    public function testUpdateProductReturnsErrorForMissingProduct(): void
    {
        $this->productRepository->getOneBy(Argument::cetera())
            ->willThrow(new ProductNotFoundException(['uuid' => 'missing']));

        $result = $this->tool->updateProduct('missing', 'en', title: 'x');

        $this->assertArrayHasKey('error', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('missing', $result['error']);
        $this->assertNotEmpty($result['hint']);
    }

    public function testUpdateProductMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ProductUpdateTool::class, 'updateProduct');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'updateProduct() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_product_update', $instance->name);
    }

    /**
     * @param array<string, mixed> $currentData
     *
     * @return \Closure(): ?object
     */
    private function givenProduct(array $currentData): \Closure
    {
        $product = new Product('uuid-1');

        $this->productRepository->getOneBy(Argument::cetera())->willReturn($product);
        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn($currentData);

        $captured = null;
        $this->messageBus->dispatch(Argument::type(Envelope::class), Argument::cetera())
            ->will(function(array $args) use ($product, &$captured): Envelope {
                /** @var Envelope $envelope */
                $envelope = $args[0];
                $captured = $envelope->getMessage();

                return $envelope->with(new HandledStamp($product, 'handler'));
            });

        return static function() use (&$captured): ?object {
            return $captured;
        };
    }

    private function formMetadataProvider(): ArrayMetadataProvider
    {
        $provider = new ArrayMetadataProvider();
        $provider->setDefault(new FormMetadata());

        return $provider;
    }
}
