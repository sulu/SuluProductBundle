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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Trash;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Sulu\Content\Application\ContentMerger\ContentMergerInterface;
use Sulu\Content\Application\ContentNormalizer\ContentNormalizerInterface;
use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Sulu\Content\Domain\Model\DimensionContentCollection;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Event\ProductRestoredEvent;
use Sulu\Product\Domain\Event\ProductTranslationRestoredEvent;
use Sulu\Product\Domain\Exception\ProductVariantParentNotFoundException;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Sulu\Trash\ProductRestoreResult;
use Sulu\Product\Infrastructure\Sulu\Trash\ProductTrashItemHandler;

#[CoversClass(ProductTrashItemHandler::class)]
class ProductTrashItemHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TrashItemRepositoryInterface> */
    private ObjectProphecy $trashItemRepository;

    /** @var ObjectProphecy<ProductRepositoryInterface> */
    private ObjectProphecy $productRepository;

    /** @var ObjectProphecy<ContentNormalizerInterface> */
    private ObjectProphecy $contentNormalizer;

    /** @var ObjectProphecy<ContentMergerInterface> */
    private ObjectProphecy $contentMerger;

    /** @var ObjectProphecy<ContentPersisterInterface> */
    private ObjectProphecy $contentPersister;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $domainEventCollector;

    private ProductTrashItemHandler $handler;

    protected function setUp(): void
    {
        $this->trashItemRepository = $this->prophesize(TrashItemRepositoryInterface::class);
        $this->productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $this->contentNormalizer = $this->prophesize(ContentNormalizerInterface::class);
        $this->contentMerger = $this->prophesize(ContentMergerInterface::class);
        $this->contentPersister = $this->prophesize(ContentPersisterInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $this->handler = new ProductTrashItemHandler(
            $this->trashItemRepository->reveal(),
            $this->productRepository->reveal(),
            $this->contentNormalizer->reveal(),
            $this->contentMerger->reveal(),
            $this->contentPersister->reveal(),
            $this->domainEventCollector->reveal(),
        );
    }

    public function testGetResourceKey(): void
    {
        $this->assertSame(ProductInterface::RESOURCE_KEY, ProductTrashItemHandler::getResourceKey());
    }

    public function testGetConfiguration(): void
    {
        $configuration = $this->handler->getConfiguration();

        $this->assertSame(ProductAdmin::EDIT_TABS_VIEW, $configuration->getView());
        $this->assertSame(['id' => 'id', 'locale' => 'locale'], $configuration->getResultToView());
    }

    public function testRestoreCreatesProductWhenNotFound(): void
    {
        $product = new Product('uuid-restore');

        $trashItem = $this->prophesize(TrashItemInterface::class);
        $trashItem->getRestoreData()->willReturn([
            'productFamily' => 'family-uuid',
            'dimensionContents' => [
                ['locale' => 'en', 'title' => 'Title'],
            ],
        ]);
        $trashItem->getResourceId()->willReturn('uuid-restore');
        $trashItem->getRestoreType()->willReturn(null);

        $this->productRepository->findOneBy(['uuid' => 'uuid-restore'])->willReturn(null);
        $this->productRepository->createNew('uuid-restore')->willReturn($product);
        $this->productRepository->add($product)->shouldBeCalled();
        $this->contentPersister->persist($product, Argument::type('array'), Argument::type('array'))
            ->shouldBeCalled();

        $this->domainEventCollector->collect(Argument::type(ProductRestoredEvent::class))
            ->shouldBeCalled();

        $restored = $this->handler->restore($trashItem->reveal());

        $this->assertEquals(new ProductRestoreResult('uuid-restore', 'en'), $restored);
    }

    public function testRestoreUsesExistingProductWhenFound(): void
    {
        $product = new Product('uuid-restore');

        $trashItem = $this->prophesize(TrashItemInterface::class);
        $trashItem->getRestoreData()->willReturn(['dimensionContents' => [['locale' => 'de']]]);
        $trashItem->getResourceId()->willReturn('uuid-restore');
        $trashItem->getRestoreType()->willReturn(null);

        $this->productRepository->findOneBy(['uuid' => 'uuid-restore'])->willReturn($product);
        $this->productRepository->createNew(Argument::cetera())->shouldNotBeCalled();
        $this->productRepository->add(Argument::any())->shouldNotBeCalled();
        $this->contentPersister->persist($product, ['locale' => 'de'], Argument::type('array'))->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::type(ProductRestoredEvent::class))->shouldBeCalled();

        $restored = $this->handler->restore($trashItem->reveal());

        $this->assertEquals(new ProductRestoreResult('uuid-restore', 'de'), $restored);
    }

    public function testRestoreReattachesVariantParentAndType(): void
    {
        $product = new Product('variant-uuid');
        $parent = new Product('parent-uuid');

        $trashItem = $this->prophesize(TrashItemInterface::class);
        $trashItem->getRestoreData()->willReturn([
            'parent' => 'parent-uuid',
            'type' => ProductInterface::TYPE_VARIANT,
            'position' => 3,
            'dimensionContents' => [['locale' => 'en']],
        ]);
        $trashItem->getResourceId()->willReturn('variant-uuid');
        $trashItem->getRestoreType()->willReturn(null);

        $this->productRepository->findOneBy(['uuid' => 'variant-uuid'])->willReturn($product);
        $this->productRepository->findOneBy(['uuid' => 'parent-uuid'])->willReturn($parent);
        $this->contentPersister->persist($product, Argument::type('array'), Argument::type('array'))->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::type(ProductRestoredEvent::class))->shouldBeCalled();

        $restored = $this->handler->restore($trashItem->reveal());

        // A variant is edited in its parent's variants tab.
        $this->assertEquals(new ProductRestoreResult('parent-uuid', 'en'), $restored);
        $this->assertSame($parent, $product->getParent());
        $this->assertSame(ProductInterface::TYPE_VARIANT, $product->getType());
        $this->assertSame(3, $product->getPosition());
    }

    public function testRestoreRefusesVariantWhoseParentIsMissing(): void
    {
        $trashItem = $this->prophesize(TrashItemInterface::class);
        $trashItem->getRestoreData()->willReturn([
            'parent' => 'parent-uuid',
            'type' => ProductInterface::TYPE_VARIANT,
            'dimensionContents' => [['locale' => 'en']],
        ]);
        $trashItem->getResourceId()->willReturn('variant-uuid');
        $trashItem->getRestoreType()->willReturn(null);

        $this->productRepository->findOneBy(['uuid' => 'parent-uuid'])->willReturn(null);
        $this->productRepository->add(Argument::any())->shouldNotBeCalled();
        $this->contentPersister->persist(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(ProductVariantParentNotFoundException::class);

        $this->handler->restore($trashItem->reveal());
    }

    public function testRestoreBringsBackEmbeddedVariants(): void
    {
        $parent = new Product('parent-uuid');
        $variant = new Product('variant-uuid');

        $variantData = [
            'uuid' => 'variant-uuid',
            'position' => 2,
            'dimensionContents' => [['locale' => 'en', 'title' => 'Variant L']],
        ];
        $trashItem = $this->prophesize(TrashItemInterface::class);
        $trashItem->getRestoreData()->willReturn([
            'type' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
            'parent' => null,
            'dimensionContents' => [['locale' => 'en', 'title' => 'Parent']],
            'variants' => [$variantData],
        ]);
        $trashItem->getResourceId()->willReturn('parent-uuid');
        $trashItem->getRestoreType()->willReturn(null);

        $this->productRepository->findOneBy(['uuid' => 'parent-uuid'])->willReturn(null);
        $this->productRepository->createNew('parent-uuid')->willReturn($parent);
        $this->productRepository->add($parent)->shouldBeCalled();
        $this->productRepository->findOneBy(['uuid' => 'variant-uuid'])->willReturn(null);
        $this->productRepository->createNew('variant-uuid')->willReturn($variant);
        $this->productRepository->add($variant)->shouldBeCalled();
        $this->contentPersister->persist($parent, Argument::type('array'), Argument::type('array'))->shouldBeCalledOnce();
        $this->contentPersister->persist($variant, ['locale' => 'en', 'title' => 'Variant L'], Argument::type('array'))->shouldBeCalledOnce();

        $this->domainEventCollector->collect(Argument::that(
            static fn ($event) => $event instanceof ProductRestoredEvent
                && 'parent-uuid' === $event->getResourceId()
                && !\array_key_exists('variants', $event->getEventPayload() ?? []),
        ))->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::that(
            static fn ($event) => $event instanceof ProductRestoredEvent
                && 'variant-uuid' === $event->getResourceId()
                && 'Variant L' === $event->getResourceTitle(),
        ))->shouldBeCalledOnce();

        $restored = $this->handler->restore($trashItem->reveal());

        $this->assertEquals(new ProductRestoreResult('parent-uuid', 'en'), $restored);
        $this->assertSame(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, $parent->getType());
        $this->assertSame($parent, $variant->getParent());
        $this->assertSame(ProductInterface::TYPE_VARIANT, $variant->getType());
        $this->assertSame(2, $variant->getPosition());
    }

    public function testRestoreEmitsTranslationEventWhenRestoreTypeIsTranslation(): void
    {
        $product = new Product('uuid-restore');

        $trashItem = $this->prophesize(TrashItemInterface::class);
        $trashItem->getRestoreData()->willReturn([
            'dimensionContents' => [
                ['locale' => 'en'],
                ['locale' => 'de'],
            ],
        ]);
        $trashItem->getResourceId()->willReturn('uuid-restore');
        $trashItem->getRestoreType()->willReturn('translation');

        $this->productRepository->findOneBy(['uuid' => 'uuid-restore'])->willReturn($product);
        $this->contentPersister->persist($product, Argument::type('array'), Argument::type('array'))
            ->shouldBeCalledTimes(2);

        $this->domainEventCollector->collect(Argument::type(ProductTranslationRestoredEvent::class))
            ->shouldBeCalledTimes(2);

        $restored = $this->handler->restore($trashItem->reveal());

        $this->assertEquals(new ProductRestoreResult('uuid-restore', 'en'), $restored);
    }

    public function testRestoreTranslationKeepsTheCurrentPosition(): void
    {
        $parent = new Product('parent-uuid');
        $variant = new Product('variant-uuid');
        $variant->setPosition(5);

        $trashItem = $this->prophesize(TrashItemInterface::class);
        $trashItem->getRestoreData()->willReturn([
            'parent' => 'parent-uuid',
            'type' => ProductInterface::TYPE_VARIANT,
            'position' => 1,
            'dimensionContents' => [['locale' => 'de']],
        ]);
        $trashItem->getResourceId()->willReturn('variant-uuid');
        $trashItem->getRestoreType()->willReturn('translation');

        $this->productRepository->findOneBy(['uuid' => 'variant-uuid'])->willReturn($variant);
        $this->productRepository->findOneBy(['uuid' => 'parent-uuid'])->willReturn($parent);
        $this->contentPersister->persist($variant, Argument::type('array'), Argument::type('array'))->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::type(ProductTranslationRestoredEvent::class))->shouldBeCalledOnce();

        $this->handler->restore($trashItem->reveal());

        $this->assertSame(5, $variant->getPosition());
    }

    public function testStoreCreatesTrashItemForProduct(): void
    {
        $product = new Product('store-uuid');

        $unlocalizedContent = new ProductDimensionContent($product);
        $unlocalizedContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $unlocalizedContent->addAvailableLocale('en');
        $product->addDimensionContent($unlocalizedContent);

        $enContent = new ProductDimensionContent($product);
        $enContent->setLocale('en');
        $enContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $product->addDimensionContent($enContent);

        $mergedContent = $this->prophesize(ProductDimensionContentInterface::class);
        $this->contentMerger->merge(Argument::type(DimensionContentCollection::class))
            ->willReturn($mergedContent->reveal());
        $this->contentNormalizer->normalize($mergedContent->reveal())
            ->willReturn(['locale' => 'en']);

        $trashItem = $this->prophesize(TrashItemInterface::class);
        $this->trashItemRepository->create(
            ProductInterface::RESOURCE_KEY,
            'store-uuid',
            Argument::type('array'),
            Argument::type('array'),
            null,
            [],
            ProductAdmin::SECURITY_CONTEXT,
            null,
            'store-uuid',
        )->willReturn($trashItem->reveal());

        $result = $this->handler->store($product, []);

        $this->assertSame($trashItem->reveal(), $result);
    }

    public function testStoreSkipsNonDraftDimensionContents(): void
    {
        $product = new Product('store-uuid-2');

        $unlocalizedContent = new ProductDimensionContent($product);
        $unlocalizedContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $unlocalizedContent->addAvailableLocale('en');
        $product->addDimensionContent($unlocalizedContent);

        $enContent = new ProductDimensionContent($product);
        $enContent->setLocale('en');
        $enContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $product->addDimensionContent($enContent);

        // live content — should be skipped
        $liveContent = new ProductDimensionContent($product);
        $liveContent->setLocale('en');
        $liveContent->setStage(DimensionContentInterface::STAGE_LIVE);
        $product->addDimensionContent($liveContent);

        // wrong version — should be skipped
        $wrongVersionContent = new ProductDimensionContent($product);
        $wrongVersionContent->setLocale('fr');
        $wrongVersionContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $wrongVersionContent->setVersion(1);
        $product->addDimensionContent($wrongVersionContent);

        $mergedContent = $this->prophesize(ProductDimensionContentInterface::class);
        $this->contentMerger->merge(Argument::type(DimensionContentCollection::class))
            ->willReturn($mergedContent->reveal());
        $this->contentNormalizer->normalize($mergedContent->reveal())
            ->willReturn(['locale' => 'en']);

        $trashItem = $this->prophesize(TrashItemInterface::class);
        $this->trashItemRepository->create(
            ProductInterface::RESOURCE_KEY,
            Argument::type('string'),
            Argument::type('array'),
            Argument::type('array'),
            Argument::any(),
            Argument::any(),
            Argument::any(),
            null,
            Argument::type('string'),
        )->willReturn($trashItem->reveal());

        // Should not throw — live and wrong-version contents are filtered out
        $result = $this->handler->store($product, []);

        $this->assertSame($trashItem->reveal(), $result);
    }

    public function testStoreCreatesTrashItemForTranslation(): void
    {
        $product = new Product('store-uuid-3');

        $unlocalizedContent = new ProductDimensionContent($product);
        $unlocalizedContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $unlocalizedContent->addAvailableLocale('en');
        $unlocalizedContent->addAvailableLocale('de');
        $product->addDimensionContent($unlocalizedContent);

        $enContent = new ProductDimensionContent($product);
        $enContent->setLocale('en');
        $enContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $product->addDimensionContent($enContent);

        // 'de' content — should be skipped because options['locale'] = 'en'
        $deContent = new ProductDimensionContent($product);
        $deContent->setLocale('de');
        $deContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $product->addDimensionContent($deContent);

        $mergedContent = $this->prophesize(ProductDimensionContentInterface::class);
        $this->contentMerger->merge(Argument::type(DimensionContentCollection::class))
            ->willReturn($mergedContent->reveal());
        $this->contentNormalizer->normalize($mergedContent->reveal())
            ->willReturn(['locale' => 'en']);

        $trashItem = $this->prophesize(TrashItemInterface::class);
        $this->trashItemRepository->create(
            ProductInterface::RESOURCE_KEY,
            'store-uuid-3',
            Argument::type('array'),
            Argument::type('array'),
            'translation',
            ['locale' => 'en'],
            ProductAdmin::SECURITY_CONTEXT,
            null,
            'store-uuid-3',
        )->willReturn($trashItem->reveal());

        $result = $this->handler->store($product, ['locale' => 'en']);

        $this->assertSame($trashItem->reveal(), $result);
    }

    public function testStoreIncludesTitlesWhenPresent(): void
    {
        $product = new Product('store-uuid-4');

        $unlocalizedContent = new ProductDimensionContent($product);
        $unlocalizedContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $unlocalizedContent->addAvailableLocale('en');
        $product->addDimensionContent($unlocalizedContent);

        $enContent = new ProductDimensionContent($product);
        $enContent->setLocale('en');
        $enContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $enContent->setTemplateData(['title' => 'My Title']);
        $product->addDimensionContent($enContent);

        $mergedContent = $this->prophesize(ProductDimensionContentInterface::class);
        $this->contentMerger->merge(Argument::type(DimensionContentCollection::class))
            ->willReturn($mergedContent->reveal());
        $this->contentNormalizer->normalize($mergedContent->reveal())
            ->willReturn(['locale' => 'en', 'title' => 'My Title']);

        $trashItem = $this->prophesize(TrashItemInterface::class);
        $this->trashItemRepository->create(
            ProductInterface::RESOURCE_KEY,
            'store-uuid-4',
            Argument::that(fn (array $titles) => isset($titles['en']) && 'My Title' === $titles['en']),
            Argument::type('array'),
            null,
            [],
            ProductAdmin::SECURITY_CONTEXT,
            null,
            'store-uuid-4',
        )->willReturn($trashItem->reveal());

        $result = $this->handler->store($product, []);

        $this->assertSame($trashItem->reveal(), $result);
    }

    public function testStoreEmbedsVariantsInTheParentsTrashItem(): void
    {
        $product = new Product('parent-uuid');
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $this->addDraftContent($product, 'en');

        $variant = new Product('variant-uuid');
        $variant->setType(ProductInterface::TYPE_VARIANT);
        $variant->setParent($product);
        $variant->setPosition(4);
        $this->addDraftContent($variant, 'en');
        $this->productRepository->findBy(['parent' => 'parent-uuid'])->willReturn([$variant]);

        $mergedContent = $this->prophesize(ProductDimensionContentInterface::class);
        $this->contentMerger->merge(Argument::type(DimensionContentCollection::class))
            ->willReturn($mergedContent->reveal());
        $this->contentNormalizer->normalize($mergedContent->reveal())
            ->willReturn(['locale' => 'en']);

        $trashItem = $this->prophesize(TrashItemInterface::class);
        $this->trashItemRepository->create(
            ProductInterface::RESOURCE_KEY,
            'parent-uuid',
            Argument::type('array'),
            Argument::that(static fn (array $data) => [[
                'uuid' => 'variant-uuid',
                'position' => 4,
                'dimensionContents' => [['locale' => 'en']],
            ]] === $data['variants']),
            null,
            [],
            ProductAdmin::SECURITY_CONTEXT,
            null,
            'parent-uuid',
        )->willReturn($trashItem->reveal());

        $this->assertSame($trashItem->reveal(), $this->handler->store($product, []));
    }

    private function addDraftContent(Product $product, string $locale): void
    {
        $unlocalizedContent = new ProductDimensionContent($product);
        $unlocalizedContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $unlocalizedContent->addAvailableLocale($locale);
        $product->addDimensionContent($unlocalizedContent);

        $localizedContent = new ProductDimensionContent($product);
        $localizedContent->setLocale($locale);
        $localizedContent->setStage(DimensionContentInterface::STAGE_DRAFT);
        $product->addDimensionContent($localizedContent);
    }
}
