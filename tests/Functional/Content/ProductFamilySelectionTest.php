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

namespace Sulu\Product\Tests\Functional\Content;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Content\Application\PropertyResolver\PropertyResolverProviderInterface;
use Sulu\Content\Application\ResourceLoader\ResourceLoaderProvider;
use Sulu\Content\Tests\Functional\Traits\CreateMediaTrait;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\ProductFamilySelectionPropertyResolver;
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\SingleProductFamilySelectionPropertyResolver;

#[CoversNothing]
class ProductFamilySelectionTest extends SuluTestCase
{
    use CreateMediaTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        self::purgeDatabase();
    }

    public function testBothSelectionTypesHaveAResolver(): void
    {
        /** @var PropertyResolverProviderInterface $provider */
        $provider = self::getContainer()->get('sulu_content.property_resolver_provider');

        $this->assertInstanceOf(
            ProductFamilySelectionPropertyResolver::class,
            $provider->getPropertyResolver('product_family_selection'),
        );
        $this->assertInstanceOf(
            SingleProductFamilySelectionPropertyResolver::class,
            $provider->getPropertyResolver('single_product_family_selection'),
        );
    }

    public function testTheResolvedIdsLoadFromTheDatabase(): void
    {
        /** @var ProductFamilyRepositoryInterface $repository */
        $repository = self::getContainer()->get(ProductFamilyRepositoryInterface::class);
        $family = $repository->create();
        $family->setExternalIdentifier('SPK');
        $family->addTranslation(new ProductFamilyTranslation($family, 'en', 'speakON'));
        $repository->save($family);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->flush();
        $entityManager->clear();

        /** @var string $uuid */
        $uuid = $family->getUuid();

        /** @var ResourceLoaderProvider $loaders */
        $loaders = self::getContainer()->get('sulu_content.resource_loader_provider');
        $resolvable = (new ProductFamilySelectionPropertyResolver())->resolve([$uuid, 'unknown'], 'en')->getContent();
        $this->assertIsArray($resolvable);
        $this->assertInstanceOf(ResolvableResource::class, $resolvable[0]);

        $loader = $loaders->getResourceLoader($resolvable[0]->getResourceLoaderKey());
        $this->assertNotNull($loader);
        $loaded = $loader->load([$uuid, 'unknown'], 'en');

        $this->assertSame([$uuid], \array_keys($loaded));
        $this->assertInstanceOf(ContentView::class, $loaded[$uuid]);
        $content = $loaded[$uuid]->getContent();
        $this->assertIsArray($content);
        $this->assertSame(['uuid', 'externalIdentifier', 'name', 'image'], \array_keys($content));
        $this->assertSame([$uuid, 'SPK', 'speakON'], [$content['uuid'], $content['externalIdentifier'], $content['name']]);
    }

    /** A selected family resolves to the flat shape of a product's `productFamily`, with its image loaded as media. */
    public function testASelectedFamilyResolvesWithItsImage(): void
    {
        $container = self::getContainer();
        $media = self::createMedia(self::createCollection());

        /** @var ProductFamilyRepositoryInterface $familyRepository */
        $familyRepository = $container->get(ProductFamilyRepositoryInterface::class);
        $family = $familyRepository->create();
        $family->setExternalIdentifier('SPK');
        $family->setImage($media);
        $family->addTranslation(new ProductFamilyTranslation($family, 'en', 'speakON'));
        $familyRepository->save($family);

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get('sulu_product.product_repository');
        $product = $productRepository->createNew();
        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('draft');
        $dimensionContent->setTemplateKey('product');
        $dimensionContent->setTemplateData(['title' => 'NL4FX', 'productFamilies' => [$family->getUuid()]]);
        $product->addDimensionContent($dimensionContent);
        $productRepository->add($product);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $entityManager->persist($dimensionContent);
        $entityManager->flush();

        /** @var ContentResolverInterface $contentResolver */
        $contentResolver = $container->get('sulu_content.content_resolver');
        $result = $contentResolver->resolve($dimensionContent);

        $content = $result['content'];
        $this->assertIsArray($content['productFamilies']);
        $this->assertCount(1, $content['productFamilies']);
        $selected = $content['productFamilies'][0];
        $this->assertIsArray($selected);
        $this->assertSame(['uuid', 'externalIdentifier', 'name', 'image'], \array_keys($selected));
        $this->assertSame([$family->getUuid(), 'SPK', 'speakON'], [$selected['uuid'], $selected['externalIdentifier'], $selected['name']]);
        $this->assertInstanceOf(Media::class, $selected['image']);
        $this->assertSame($media->getId(), $selected['image']->getId());
    }
}
