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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\ResourceLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Infrastructure\Sulu\Content\ResourceLoader\MediaResourceLoader;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductFamilyContentViewFactory;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductFamilyResourceLoader;

#[CoversClass(ProductFamilyResourceLoader::class)]
#[CoversClass(ProductFamilyContentViewFactory::class)]
class ProductFamilyResourceLoaderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;

    private ProductFamilyResourceLoader $loader;

    public function setUp(): void
    {
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->loader = new ProductFamilyResourceLoader($this->productFamilyRepository->reveal());
    }

    public function testGetKey(): void
    {
        $this->assertSame('product_family', ProductFamilyResourceLoader::getKey());
    }

    public function testLoadMapsFamiliesByUuidInTheRequestedLocale(): void
    {
        $speakon = $this->createFamily('uuid-1', 'SPK', ['en' => 'speakON', 'de' => 'speakON DE']);
        $ethercon = $this->createFamily('uuid-2', null, ['de' => 'etherCON']);

        $this->productFamilyRepository->findBy(['uuids' => ['uuid-1', 'uuid-2']])
            ->willReturn([$speakon, $ethercon])
            ->shouldBeCalledOnce();

        $loaded = $this->loader->load(['uuid-1', 'uuid-2'], 'en');

        $this->assertSame(['uuid-1', 'uuid-2'], \array_keys($loaded));
        $this->assertSame(
            ['uuid' => 'uuid-1', 'externalIdentifier' => 'SPK', 'name' => 'speakON'],
            $this->scalarContent($loaded['uuid-1']),
        );
        $this->assertSame(
            ['uuid' => 'uuid-2', 'externalIdentifier' => null, 'name' => null],
            $this->scalarContent($loaded['uuid-2']),
        );
    }

    /** The image stays a media resolvable, so the content resolver loads it and tags the page without a reference. */
    public function testLoadResolvesTheImageAsMedia(): void
    {
        $image = $this->createStub(MediaInterface::class);
        $image->method('getId')->willReturn(5);
        $family = $this->createFamily('uuid-1', 'SPK', ['en' => 'speakON']);
        $family->setImage($image);

        $this->productFamilyRepository->findBy(['uuids' => ['uuid-1']])->willReturn([$family]);

        $content = $this->loader->load(['uuid-1'], 'en')['uuid-1']->getContent();
        $this->assertIsArray($content);
        $imageView = $content['image'];
        $this->assertInstanceOf(ContentView::class, $imageView);

        $resource = $imageView->getContent();
        $this->assertInstanceOf(ResolvableResource::class, $resource);
        $this->assertSame(5, $resource->getId());
        $this->assertSame(MediaResourceLoader::getKey(), $resource->getResourceLoaderKey());
        $this->assertSame(MediaInterface::RESOURCE_KEY, $resource->getResourceKey());
        $this->assertSame([], $imageView->getReferences());
    }

    public function testLoadWithoutImageResolvesAnEmptyImage(): void
    {
        $this->productFamilyRepository->findBy(['uuids' => ['uuid-1']])
            ->willReturn([$this->createFamily('uuid-1', 'SPK', ['en' => 'speakON'])]);

        $content = $this->loader->load(['uuid-1'], 'en')['uuid-1']->getContent();
        $this->assertIsArray($content);
        $this->assertInstanceOf(ContentView::class, $content['image']);
        $this->assertNull($content['image']->getContent());
    }

    public function testLoadSkipsAFamilyWithoutUuid(): void
    {
        $this->productFamilyRepository->findBy(['uuids' => ['uuid-1']])
            ->willReturn([new ProductFamily()]);

        $this->assertSame([], $this->loader->load(['uuid-1'], 'en'));
    }

    public function testLoadWithoutLocaleQueriesNothing(): void
    {
        $this->productFamilyRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $this->assertSame([], $this->loader->load(['uuid-1'], null));
    }

    public function testLoadWithoutIdsQueriesNothing(): void
    {
        $this->productFamilyRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $this->assertSame([], $this->loader->load([], 'en'));
    }

    /**
     * @return mixed[]
     */
    private function scalarContent(ContentView $view): array
    {
        $content = $view->getContent();
        $this->assertIsArray($content);
        unset($content['image']);

        return $content;
    }

    /**
     * @param array<string, string> $names
     */
    private function createFamily(string $uuid, ?string $externalIdentifier, array $names): ProductFamily
    {
        $family = new ProductFamily();
        $family->setUuid($uuid);
        $family->setExternalIdentifier($externalIdentifier);
        foreach ($names as $locale => $name) {
            $family->addTranslation(new ProductFamilyTranslation($family, $locale, $name));
        }

        return $family;
    }
}
