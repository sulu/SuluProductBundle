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
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Mcp\Application\AdminLink\AdminLinkGeneratorInterface;
use Sulu\Mcp\Application\Content\BlockDataValidator;
use Sulu\Mcp\Application\Content\ContentMetadataMapper;
use Sulu\Mcp\Application\Metadata\MetadataLocaleResolver;
use Sulu\Product\Application\Mcp\ProductAssociationResolver;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductCreateTool;
use Sulu\Product\Tests\Unit\Fixture\ArrayMetadataProvider;
use Sulu\Product\Tests\Unit\Fixture\CompletenessCheckerFactory;
use Sulu\Product\Tests\Unit\Fixture\FixedBlockIdGenerator;
use Sulu\Product\Tests\Unit\Fixture\ProductContentMetadata;
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
            $this->associationResolver(),
            CompletenessCheckerFactory::create(),
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

    public function testCreateProductListsRecommendationsForAnIncompleteProduct(): void
    {
        $this->expectDispatch(new Product('new-uuid'));
        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn(['title' => 'Shirt']);

        $result = $this->tool->createProduct('en', 'family-uuid', 'Shirt');

        $this->assertIsArray($result['recommendations'] ?? null);
        $this->assertStringContainsString('No code.', \implode("\n", \array_filter($result['recommendations'], 'is_string')));
    }

    public function testCreateProductOmitsRecommendationsForACompleteProduct(): void
    {
        $this->expectDispatch(new Product('new-uuid'));
        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn([
            'title' => 'Shirt',
            'code' => 'S-1',
            'url' => ['page' => ['uuid' => 'p', 'path' => '/products'], 'suffix' => '/shirt'],
            'excerptCategories' => [1],
            'excerptTags' => [2],
            'seo' => ['title' => 'Shirt', 'description' => 'A shirt'],
        ]);

        $result = $this->tool->createProduct('en', 'family-uuid', 'Shirt');

        $this->assertArrayNotHasKey('recommendations', $result);
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
            attributes: ['colour-uuid' => 'red'],
        );

        $this->assertInstanceOf(CreateProductMessage::class, $captured);
        $data = $captured->getData();
        $this->assertSame('family-uuid', $data['productFamily']);
        $this->assertSame('SHIRT-1', $data['code'] ?? null);
        $this->assertSame(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, $data['type'] ?? null);
        $this->assertSame(['colour-uuid' => 'red'], $data['attributes'] ?? null);
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

    public function testCreateProductWarnsWhenTheProductHasNoUrl(): void
    {
        $this->captureMessage(new Product('new-uuid'));

        $result = $this->tool->createProduct('en', 'family-uuid', 'Shirt');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('sulu_product_update', \is_string($result['warning'] ?? null) ? $result['warning'] : '');
    }

    public function testCreateProductDoesNotWarnWhenTheProductHasAUrl(): void
    {
        $this->captureMessage(new Product('new-uuid'));
        $this->contentManager->normalize(Argument::cetera())->willReturn([
            'url' => ['page' => ['uuid' => 'page-uuid', 'path' => '/products'], 'suffix' => '/shirt'],
        ]);

        $result = $this->tool->createProduct('en', 'family-uuid', 'Shirt');

        $this->assertArrayNotHasKey('warning', $result);
    }

    public function testCreateProductGeneratesTheUrlSuffixFromTheTitle(): void
    {
        $captured = $this->captureMessage(new Product('new-uuid'));

        $this->tool->createProduct(
            'en',
            'family-uuid',
            'Große Monstera Deliciosa!',
            content: ['url' => ['page' => ['uuid' => 'page-uuid', 'path' => '/products']]],
        );

        $message = $captured();
        $this->assertInstanceOf(CreateProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame(
            ['page' => ['uuid' => 'page-uuid', 'path' => '/products'], 'suffix' => '/grosse-monstera-deliciosa'],
            $data['url'] ?? null,
        );
    }

    public function testCreateProductKeepsAGivenUrlSuffix(): void
    {
        $captured = $this->captureMessage(new Product('new-uuid'));
        $url = ['page' => ['uuid' => 'page-uuid', 'path' => '/products'], 'suffix' => '/custom'];

        $this->tool->createProduct('en', 'family-uuid', 'Shirt', content: ['url' => $url]);

        $message = $captured();
        $this->assertInstanceOf(CreateProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame($url, $data['url'] ?? null);
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

    public function testCreateProductResolvesAssociationsToUuids(): void
    {
        $captured = $this->captureMessage(new Product('new-uuid'));

        $result = $this->toolWithContentMetadata()->createProduct('en', 'family-uuid', 'Shirt', associations: ['accessory' => ['pot-uuid', 'BELT-1'], 'alternative' => []]);

        $this->assertTrue($result['success']);
        $message = $captured();
        $this->assertInstanceOf(CreateProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame(['accessory' => ['pot-uuid', 'belt-uuid'], 'alternative' => []], $data['associations'] ?? null);
    }

    public function testCreateProductRejectsAnUnknownAssociationType(): void
    {
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->toolWithContentMetadata()->createProduct('en', 'family-uuid', 'Shirt', associations: ['bogus' => ['pot-uuid']]);

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('bogus', $result['error']);
        $this->assertIsString($result['hint']);
        $this->assertStringContainsString('sulu_product_association_type_list', $result['hint']);
    }

    public function testCreateProductRejectsAnAssociationTargetThatDoesNotExist(): void
    {
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->toolWithContentMetadata()->createProduct('en', 'family-uuid', 'Shirt', associations: ['accessory' => ['missing']]);

        $this->assertIsString($result['error']);
        $this->assertStringContainsString('missing', $result['error']);
    }

    public function testCreateProductSendsExcerptCategoriesAndTags(): void
    {
        $captured = $this->captureMessage(new Product('new-uuid'));

        $this->toolWithContentMetadata()->createProduct('en', 'family-uuid', 'Shirt', excerpt: ['excerptCategories' => [3], 'excerptTags' => [4, 5]]);

        $message = $captured();
        $this->assertInstanceOf(CreateProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame([3], $data['excerptCategories'] ?? null);
        $this->assertSame([4, 5], $data['excerptTags'] ?? null);
    }

    public function testCreateProductSendsMediaDetails(): void
    {
        $captured = $this->captureMessage(new Product('new-uuid'));
        $details = ['image' => ['id' => 12], 'documents' => ['ids' => [34, 35]]];

        $this->toolWithContentMetadata()->createProduct('en', 'family-uuid', 'Shirt', details: $details);

        $message = $captured();
        $this->assertInstanceOf(CreateProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame($details, $data['details'] ?? null);
    }

    private function associationResolver(): ProductAssociationResolver
    {
        $repository = $this->prophesize(ProductRepositoryInterface::class);
        $repository->findOneBy(['uuid' => 'pot-uuid'])->willReturn(new Product('pot-uuid'));
        $repository->findOneBy(['uuid' => 'BELT-1'])->willReturn(null);
        $repository->findOneBy(['uuid' => 'missing'])->willReturn(null);
        $repository->findOneBy(['code' => 'BELT-1', 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT])->willReturn(new Product('belt-uuid'));
        $repository->findOneBy(['code' => 'missing', 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT])->willReturn(null);

        return new ProductAssociationResolver($repository->reveal(), new ProductAssociationTypeRegistry(['accessory' => ['label' => 'Accessory'], 'alternative' => ['label' => 'Alternative']]));
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
            $this->associationResolver(),
            CompletenessCheckerFactory::create(),
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
