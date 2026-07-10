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
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfiguration;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Sulu\Content\Application\ContentMerger\ContentMergerInterface;
use Sulu\Content\Application\ContentNormalizer\ContentNormalizerInterface;
use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Sulu\Content\Domain\Model\DimensionContentCollection;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Event\ProductRestoredEvent;
use Sulu\Product\Domain\Event\ProductTranslationRestoredEvent;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
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

        // @phpstan-ignore method.alreadyNarrowedType
        $this->assertInstanceOf(RestoreConfiguration::class, $configuration);
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

        $this->assertSame($product, $restored);
    }

    public function testRestoreUsesExistingProductWhenFound(): void
    {
        $product = new Product('uuid-restore');

        $trashItem = $this->prophesize(TrashItemInterface::class);
        $trashItem->getRestoreData()->willReturn(['dimensionContents' => []]);
        $trashItem->getResourceId()->willReturn('uuid-restore');
        $trashItem->getRestoreType()->willReturn(null);

        $this->productRepository->findOneBy(['uuid' => 'uuid-restore'])->willReturn($product);
        $this->productRepository->createNew(Argument::cetera())->shouldNotBeCalled();
        $this->productRepository->add(Argument::any())->shouldNotBeCalled();
        $this->domainEventCollector->collect(Argument::type(ProductRestoredEvent::class))->shouldBeCalled();

        $restored = $this->handler->restore($trashItem->reveal());

        $this->assertSame($product, $restored);
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

        $this->assertSame($product, $restored);
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
}
