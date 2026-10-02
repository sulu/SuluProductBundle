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
use Sulu\Product\Application\Mcp\DefaultProductUrlResolver;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductCreateTool;
use Sulu\Product\Tests\Unit\Fixture\ArrayMetadataProvider;
use Sulu\Product\Tests\Unit\Fixture\FixedBlockIdGenerator;
use Sulu\Product\Tests\Unit\Fixture\ProductContentMetadata;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorGeneratorInterface;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorRequest;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

#[CoversClass(ProductCreateTool::class)]
final class ProductCreateToolTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<MessageBusInterface> */
    private ObjectProphecy $messageBus;
    /** @var ObjectProphecy<ContentManagerInterface> */
    private ObjectProphecy $contentManager;
    private ProductCreateTool $tool;

    protected function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->contentManager = $this->prophesize(ContentManagerInterface::class);

        $this->tool = new ProductCreateTool(
            $this->messageBus->reveal(),
            $this->contentManager->reveal(),
            new ContentMetadataMapper(new ArrayMetadataProvider()),
            new BlockDataValidator($this->formMetadataProvider(), new MetadataLocaleResolver(new TokenStorage(), 'en')),
            FixedBlockIdGenerator::returning('b1', 'b2', 'b3'),
            $this->prophesize(AdminLinkGeneratorInterface::class)->reveal(),
            $this->defaultProductUrlResolver(),
        );
    }

    public function testCreateProductDispatchesACreateMessage(): void
    {
        $product = new Product('new-uuid');
        $this->expectDispatch($product);

        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn(['title' => 'Shirt']);

        $result = $this->tool->createProduct('en', 'family-uuid', 'Shirt');

        $this->assertTrue($result['success']);
        $this->assertSame('new-uuid', $result['uuid']);
    }

    public function testCreateProductSendsFamilyCodeAndAttributes(): void
    {
        $product = new Product('new-uuid');
        $captured = null;

        $this->messageBus->dispatch(Argument::type(Envelope::class), Argument::cetera())
            ->will(function(array $args) use ($product, &$captured): Envelope {
                /** @var Envelope $envelope */
                $envelope = $args[0];
                $captured = $envelope->getMessage();

                return $envelope->with(new HandledStamp($product, 'handler'));
            });

        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn([]);

        $this->tool->createProduct(
            'en',
            'family-uuid',
            'Shirt',
            code: 'SHIRT-1',
            type: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
            attributes: ['12' => 'red'],
        );

        $this->assertInstanceOf(CreateProductMessage::class, $captured);
        $data = $captured->getData();
        $this->assertSame('family-uuid', $data['productFamily']);
        $this->assertSame('SHIRT-1', $data['code'] ?? null);
        $this->assertSame(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, $data['type'] ?? null);
        $this->assertSame(['12' => 'red'], $data['attributes'] ?? null);
    }

    public function testCreateProductSetsTheShadow(): void
    {
        $product = new Product('new-uuid');
        $captured = null;

        $this->messageBus->dispatch(Argument::type(Envelope::class), Argument::cetera())
            ->will(function(array $args) use ($product, &$captured): Envelope {
                /** @var Envelope $envelope */
                $envelope = $args[0];
                $captured = $envelope->getMessage();

                return $envelope->with(new HandledStamp($product, 'handler'));
            });

        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn([]);

        $this->tool->createProduct('en', 'family-uuid', 'Shirt', shadowOn: true, shadowLocale: 'de');

        $this->assertInstanceOf(CreateProductMessage::class, $captured);
        /** @var array<string, mixed> $data */
        $data = $captured->getData();
        $this->assertTrue($data['shadowOn']);
        $this->assertSame('de', $data['shadowLocale']);
    }

    public function testCreateProductRejectsALocaleShadowingItselfWhenShadowOnIsOmitted(): void
    {
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->tool->createProduct('en', 'family-uuid', 'Shirt', shadowLocale: 'en');

        $this->assertArrayHasKey('error', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('itself', $result['error']);
    }

    public function testCreateProductRefusesToCreateAVariant(): void
    {
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->tool->createProduct('en', 'family-uuid', 'Variant', type: ProductInterface::TYPE_VARIANT);

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('variant', $result['error']);
        $this->assertIsString($result['hint']);
        $this->assertStringContainsString('sulu_product_variant_create', $result['hint']);
    }

    public function testCreateProductRefusesAnUnknownType(): void
    {
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->tool->createProduct('en', 'family-uuid', 'Thing', type: 'bundle');

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('bundle', $result['error']);
    }

    public function testCreateProductReturnsErrorOnFailure(): void
    {
        $this->messageBus->dispatch(Argument::cetera())
            ->willThrow(new \RuntimeException('Product code "SHIRT-1" is already in use'));

        $result = $this->tool->createProduct('en', 'family-uuid', 'Shirt', code: 'SHIRT-1');

        $this->assertArrayHasKey('error', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('already in use', $result['error']);
        $this->assertIsString($result['hint']);
        $this->assertNotEmpty($result['hint']);
    }

    public function testCreateProductDefaultsTheUrlFromTheTitle(): void
    {
        $captured = $this->captureMessage(new Product('new-uuid'));

        $this->tool->createProduct('en', 'family-uuid', 'Shirt');

        $this->assertSame('/products/shirt', $this->capturedData($captured)['url']);
    }

    public function testCreateProductKeepsAnExplicitUrl(): void
    {
        $captured = $this->captureMessage(new Product('new-uuid'));

        $this->tool->createProduct('en', 'family-uuid', 'Shirt', content: ['url' => '/shop/shirt']);

        $this->assertSame('/shop/shirt', $this->capturedData($captured)['url']);
    }

    public function testCreateProductWithVariantsGetsNoUrl(): void
    {
        $captured = $this->captureMessage(new Product('new-uuid'));

        $this->tool->createProduct('en', 'family-uuid', 'Shirt', type: ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $this->assertArrayNotHasKey('url', $this->capturedData($captured));
    }

    /**
     * @param \Closure(): ?object $captured
     *
     * @return array<string, mixed>
     */
    private function capturedData(\Closure $captured): array
    {
        $message = $captured();
        $this->assertInstanceOf(CreateProductMessage::class, $message);

        return $message->getData();
    }

    private function defaultProductUrlResolver(): DefaultProductUrlResolver
    {
        $generator = $this->prophesize(ResourceLocatorGeneratorInterface::class);
        $generator->generate(Argument::type(ResourceLocatorRequest::class))->willReturn('/products/shirt');

        return new DefaultProductUrlResolver($generator->reveal(), 'route', ['route_schema' => '/products/{implode(\'-\', object)}']);
    }

    public function testCreateProductMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(ProductCreateTool::class, 'createProduct');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'createProduct() must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_product_create', $instance->name);
    }

    private function expectDispatch(ProductInterface $product): void
    {
        $this->messageBus->dispatch(Argument::type(Envelope::class), Argument::cetera())
            ->will(static function(array $args) use ($product): Envelope {
                /** @var Envelope $envelope */
                $envelope = $args[0];

                return $envelope->with(new HandledStamp($product, 'handler'));
            });
    }

    private function formMetadataProvider(): ArrayMetadataProvider
    {
        $provider = new ArrayMetadataProvider();
        $provider->setDefault(new FormMetadata());

        return $provider;
    }

    public function testCreateProductSendsStatusTemplateDetailsAndBlocks(): void
    {
        $tool = $this->toolWithContentMetadata();
        $product = new Product('new-uuid');
        $captured = $this->captureMessage($product);

        $result = $tool->createProduct(
            'en',
            'family-uuid',
            'Shirt',
            status: 'available',
            template: 'default',
            content: ['blocks' => [['type' => 'text', 'title' => 'Hello']]],
            details: ['shortDescription' => 'Soft'],
        );

        $this->assertTrue($result['success']);
        $message = $captured();
        $this->assertInstanceOf(CreateProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame('available', $data['status'] ?? null);
        $this->assertSame('default', $data['template'] ?? null);
        $this->assertSame(['shortDescription' => 'Soft'], $data['details'] ?? null);
        $this->assertSame([['type' => 'text', 'title' => 'Hello', '_id' => 'b1']], $data['blocks'] ?? null);
    }

    public function testCreateProductRejectsABlockWithUnknownKeys(): void
    {
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->toolWithContentMetadata()->createProduct('en', 'family-uuid', 'Shirt', template: 'default', content: ['blocks' => [['type' => 'text', 'bogus' => 1]]]);

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('bogus', $result['error']);
    }

    public function testCreateProductRejectsAnUnknownExcerptField(): void
    {
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->toolWithContentMetadata()->createProduct('en', 'family-uuid', 'Shirt', excerpt: ['bogus' => 'x']);

        $this->assertIsString($result['error']);
        $this->assertStringContainsString('excerpt', $result['error']);
    }

    public function testCreateProductRejectsAnUnknownSeoField(): void
    {
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->toolWithContentMetadata()->createProduct('en', 'family-uuid', 'Shirt', seo: ['bogus' => 'x']);

        $this->assertIsString($result['error']);
        $this->assertStringContainsString('seo', $result['error']);
    }

    public function testCreateProductReturnsTheAdminUrl(): void
    {
        $adminLinkGenerator = $this->prophesize(AdminLinkGeneratorInterface::class);
        $adminLinkGenerator->generate('product', ['locale' => 'en', 'uuid' => 'new-uuid'])->willReturn('https://admin.example/product');
        $this->captureMessage(new Product('new-uuid'));

        $result = $this->toolWithContentMetadata($adminLinkGenerator->reveal())->createProduct('en', 'family-uuid', 'Shirt');

        $this->assertSame('https://admin.example/product', $result['admin_url'] ?? null);
    }

    private function toolWithContentMetadata(?AdminLinkGeneratorInterface $adminLinkGenerator = null): ProductCreateTool
    {
        return new ProductCreateTool(
            $this->messageBus->reveal(),
            $this->contentManager->reveal(),
            new ContentMetadataMapper(ProductContentMetadata::provider()),
            new BlockDataValidator(ProductContentMetadata::provider(), new MetadataLocaleResolver(new TokenStorage(), 'en')),
            FixedBlockIdGenerator::returning('b1', 'b2', 'b3'),
            $adminLinkGenerator ?? $this->prophesize(AdminLinkGeneratorInterface::class)->reveal(),
            $this->defaultProductUrlResolver(),
        );
    }

    /**
     * @return \Closure(): ?object
     */
    private function captureMessage(ProductInterface $product): \Closure
    {
        $captured = null;

        $this->messageBus->dispatch(Argument::type(Envelope::class), Argument::cetera())
            ->will(function(array $args) use ($product, &$captured): Envelope {
                /** @var Envelope $envelope */
                $envelope = $args[0];
                $captured = $envelope->getMessage();

                return $envelope->with(new HandledStamp($product, 'handler'));
            });

        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn([]);

        return static function() use (&$captured): ?object {
            return $captured;
        };
    }
}
