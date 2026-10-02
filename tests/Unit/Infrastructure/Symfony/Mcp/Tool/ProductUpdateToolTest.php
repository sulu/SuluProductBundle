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
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Mcp\Application\AdminLink\AdminLinkGeneratorInterface;
use Sulu\Mcp\Application\Content\BlockDataValidator;
use Sulu\Mcp\Application\Content\ContentMetadataMapper;
use Sulu\Mcp\Application\Metadata\MetadataLocaleResolver;
use Sulu\Product\Application\Mcp\ProductAssociationResolver;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Domain\Association\ProductAssociationTypeRegistry;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Mcp\Tool\ProductUpdateTool;
use Sulu\Product\Tests\Unit\Fixture\ArrayMetadataProvider;
use Sulu\Product\Tests\Unit\Fixture\FixedBlockIdGenerator;
use Sulu\Product\Tests\Unit\Fixture\ProductContentMetadata;
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
            $this->associationResolver(),
        );
    }

    public function testUpdateProductMergesAttributesIntoTheCurrentState(): void
    {
        $captured = $this->givenProduct(['title' => 'Shirt', 'attributes' => ['colour-uuid' => 'blue', 'size-uuid' => 'M']]);

        $result = $this->tool->updateProduct('uuid-1', 'en', attributes: ['size-uuid' => 'L']);

        $this->assertTrue($result['success']);

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        $this->assertSame(['colour-uuid' => 'blue', 'size-uuid' => 'L'], $message->getData()['attributes'] ?? null);
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
        $this->contentManager->resolve(Argument::cetera())->willReturn(new ProductDimensionContent(new Product()));
        $this->contentManager->normalize(Argument::cetera())->willReturn([]);
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

    public function testUpdateProductCreatesANewLocaleOfAnExistingProduct(): void
    {
        $product = new Product('uuid-1');
        $this->productRepository->getOneBy(['uuid' => 'uuid-1'], Argument::type('array'))->willReturn($product);
        $calls = 0;
        $this->contentManager->resolve(Argument::cetera())->will(function() use (&$calls, $product): ProductDimensionContent {
            if (1 === ++$calls) {
                throw new ContentNotFoundException($product, []);
            }

            return new ProductDimensionContent(new Product());
        });
        $this->contentManager->normalize(Argument::cetera())->willReturn([]);

        $captured = null;
        $this->messageBus->dispatch(Argument::type(Envelope::class), Argument::cetera())
            ->will(function(array $args) use ($product, &$captured): Envelope {
                /** @var Envelope $envelope */
                $envelope = $args[0];
                $captured = $envelope->getMessage();

                return $envelope->with(new HandledStamp($product, 'handler'));
            });

        $result = $this->tool->updateProduct('uuid-1', 'de', title: 'Hemd');

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(ModifyProductMessage::class, $captured);
        $this->assertSame('de', $captured->getLocale());
        $this->assertSame('Hemd', $captured->getData()['title'] ?? null);
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

    public function testUpdateProductSendsCodeStatusFamilyTemplateBlocksAndMergedDetails(): void
    {
        $captured = $this->givenProduct(['title' => 'Shirt', 'details' => ['shortDescription' => 'Old', 'keep' => 'yes']]);

        $result = $this->toolWithContentMetadata()->updateProduct(
            'uuid-1',
            'en',
            code: 'SHIRT-2',
            status: 'available',
            productFamily: 'family-uuid',
            template: 'default',
            content: ['blocks' => [['type' => 'text', 'title' => 'Hello']]],
            details: ['shortDescription' => 'New'],
        );

        $this->assertTrue($result['success']);
        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame('SHIRT-2', $data['code'] ?? null);
        $this->assertSame('available', $data['status'] ?? null);
        $this->assertSame('family-uuid', $data['productFamily'] ?? null);
        $this->assertSame('default', $data['template'] ?? null);
        $this->assertSame(['shortDescription' => 'New', 'keep' => 'yes'], $data['details'] ?? null);
        $this->assertSame([['type' => 'text', 'title' => 'Hello', '_id' => 'b1']], $data['blocks'] ?? null);
    }

    public function testUpdateProductRejectsABlockWithUnknownKeys(): void
    {
        $this->givenProduct([]);
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->toolWithContentMetadata()->updateProduct('uuid-1', 'en', content: ['blocks' => [['type' => 'text', 'bogus' => 1]]]);

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('bogus', $result['error']);
    }

    public function testUpdateProductRejectsAnUnknownExcerptField(): void
    {
        $this->givenProduct([]);

        $result = $this->toolWithContentMetadata()->updateProduct('uuid-1', 'en', excerpt: ['bogus' => 'x']);

        $this->assertIsString($result['error']);
        $this->assertStringContainsString('excerpt', $result['error']);
    }

    public function testUpdateProductRejectsAnUnknownSeoField(): void
    {
        $this->givenProduct([]);

        $result = $this->toolWithContentMetadata()->updateProduct('uuid-1', 'en', seo: ['bogus' => 'x']);

        $this->assertIsString($result['error']);
        $this->assertStringContainsString('seo', $result['error']);
    }

    public function testUpdateProductWarnsWhenTheProductHasNoUrl(): void
    {
        $this->givenProduct(['title' => 'Shirt']);

        $result = $this->tool->updateProduct('uuid-1', 'en', title: 'Shirt');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('sulu_product_update', \is_string($result['warning'] ?? null) ? $result['warning'] : '');
    }

    public function testUpdateProductDoesNotWarnWhenTheProductHasAUrl(): void
    {
        $this->givenProduct(['title' => 'Shirt', 'url' => ['page' => ['uuid' => 'p', 'path' => '/products'], 'suffix' => '/shirt']]);

        $result = $this->tool->updateProduct('uuid-1', 'en', title: 'Shirt');

        $this->assertArrayNotHasKey('warning', $result);
    }

    public function testUpdateProductGeneratesTheUrlSuffixFromTheTitle(): void
    {
        $captured = $this->givenProduct(['title' => 'Monstera']);

        $this->tool->updateProduct('uuid-1', 'en', content: ['url' => ['page' => ['uuid' => 'p', 'path' => '/products']]]);

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame(
            ['page' => ['uuid' => 'p', 'path' => '/products'], 'suffix' => '/monstera'],
            $data['url'] ?? null,
        );
    }

    public function testUpdateProductReturnsTheAdminUrl(): void
    {
        $adminLinkGenerator = $this->prophesize(AdminLinkGeneratorInterface::class);
        $adminLinkGenerator->generate('product', ['locale' => 'en', 'uuid' => 'uuid-1'])->willReturn('https://admin.example/product');
        $this->givenProduct([]);

        $result = $this->toolWithContentMetadata($adminLinkGenerator->reveal())->updateProduct('uuid-1', 'en', title: 'x');

        $this->assertSame('https://admin.example/product', $result['admin_url'] ?? null);
    }

    public function testUpdateProductReturnsErrorOnFailure(): void
    {
        $this->productRepository->getOneBy(Argument::cetera())->willThrow(new \RuntimeException('database gone'));

        $result = $this->tool->updateProduct('uuid-1', 'en', title: 'x');

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('database gone', $result['error']);
        $this->assertNotEmpty($result['hint']);
    }

    private function toolWithContentMetadata(?AdminLinkGeneratorInterface $adminLinkGenerator = null): ProductUpdateTool
    {
        return new ProductUpdateTool(
            $this->messageBus->reveal(),
            $this->contentManager->reveal(),
            $this->productRepository->reveal(),
            new ContentMetadataMapper(ProductContentMetadata::provider()),
            new BlockDataValidator(ProductContentMetadata::provider(), new MetadataLocaleResolver(new TokenStorage(), 'en')),
            FixedBlockIdGenerator::returning('b1', 'b2', 'b3'),
            $adminLinkGenerator ?? $this->prophesize(AdminLinkGeneratorInterface::class)->reveal(),
            $this->associationResolver(),
        );
    }

    public function testUpdateProductReplacesOnlyThePassedAssociationTypes(): void
    {
        $captured = $this->givenProduct(['title' => 'Shirt', 'associations' => ['accessory' => ['old-uuid'], 'alternative' => ['alt-uuid']]]);

        $result = $this->tool->updateProduct('uuid-1', 'en', associations: ['accessory' => ['pot-uuid', 'BELT-1']]);

        $this->assertTrue($result['success']);
        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame(['accessory' => ['pot-uuid', 'belt-uuid'], 'alternative' => ['alt-uuid']], $data['associations'] ?? null);
    }

    public function testUpdateProductClearsAnAssociationTypeWithAnEmptyList(): void
    {
        $captured = $this->givenProduct(['associations' => ['accessory' => ['old-uuid']]]);

        $this->tool->updateProduct('uuid-1', 'en', associations: ['accessory' => []]);

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame(['accessory' => []], $data['associations'] ?? null);
    }

    public function testUpdateProductLeavesAssociationsAloneWhenNoneArePassed(): void
    {
        $captured = $this->givenProduct(['associations' => ['accessory' => ['old-uuid']]]);

        $this->tool->updateProduct('uuid-1', 'en', title: 'x');

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame(['accessory' => ['old-uuid']], $data['associations'] ?? null);
    }

    public function testUpdateProductRejectsAnAssociationWithItself(): void
    {
        $this->givenProduct([]);
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->tool->updateProduct('uuid-1', 'en', associations: ['alternative' => ['uuid-1']]);

        $this->assertArrayNotHasKey('success', $result);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('itself', $result['error']);
    }

    public function testUpdateProductRejectsAnUnknownAssociationType(): void
    {
        $this->givenProduct([]);
        $this->messageBus->dispatch(Argument::cetera())->shouldNotBeCalled();

        $result = $this->tool->updateProduct('uuid-1', 'en', associations: ['bogus' => ['pot-uuid']]);

        $this->assertIsString($result['error']);
        $this->assertStringContainsString('bogus', $result['error']);
        $this->assertIsString($result['hint']);
        $this->assertStringContainsString('sulu_product_association_type_list', $result['hint']);
    }

    public function testUpdateProductSendsExcerptCategoriesAndTags(): void
    {
        $captured = $this->givenProduct(['excerptCategories' => [1]]);

        $this->toolWithContentMetadata()->updateProduct('uuid-1', 'en', excerpt: ['excerptCategories' => [3], 'excerptTags' => [4]]);

        $message = $captured();
        $this->assertInstanceOf(ModifyProductMessage::class, $message);
        /** @var array<string, mixed> $data */
        $data = $message->getData();
        $this->assertSame([3], $data['excerptCategories'] ?? null);
        $this->assertSame([4], $data['excerptTags'] ?? null);
    }

    private function associationResolver(): ProductAssociationResolver
    {
        $this->productRepository->findOneBy(['uuid' => 'pot-uuid'])->willReturn(new Product('pot-uuid'));
        $this->productRepository->findOneBy(['uuid' => 'uuid-1'])->willReturn(new Product('uuid-1'));
        $this->productRepository->findOneBy(['uuid' => 'BELT-1'])->willReturn(null);
        $this->productRepository->findOneBy(['code' => 'BELT-1', 'locale' => 'en', 'stage' => DimensionContentInterface::STAGE_DRAFT])->willReturn(new Product('belt-uuid'));

        return new ProductAssociationResolver($this->productRepository->reveal(), new ProductAssociationTypeRegistry(['accessory' => ['label' => 'Accessory'], 'alternative' => ['label' => 'Alternative']]));
    }
}
