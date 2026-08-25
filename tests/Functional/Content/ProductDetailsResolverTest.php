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
use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\MediaBundle\Api\Media;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Content\Tests\Functional\Traits\CreateMediaTrait;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ProductFamilyWrapper;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductDetailsResolver;

#[CoversClass(ProductDetailsResolver::class)]
class ProductDetailsResolverTest extends SuluTestCase
{
    use CreateMediaTrait;

    private ContentResolverInterface $contentResolver;

    private EntityManagerInterface $entityManager;

    private ProductRepositoryInterface $productRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ContentResolverInterface $contentResolver */
        $contentResolver = $container->get('sulu_content.content_resolver');
        $this->contentResolver = $contentResolver;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get('sulu_product.product_repository');
        $this->productRepository = $productRepository;

        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testProductDetailsAppearUnderRootProduct(): void
    {
        $product = $this->productRepository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('draft');
        // TemplateResolver needs a registered template key to resolve the template section
        $dimensionContent->setTemplateKey('product');
        $dimensionContent->setCode('SKU-FE');
        $dimensionContent->setStatus('available');
        $dimensionContent->setDetailsData(['shortDescription' => '<p>hi</p>']);
        $product->addDimensionContent($dimensionContent);

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($dimensionContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);
        self::assertSame('SKU-FE', $productData['code']);
        self::assertSame('available', $productData['status']);
        self::assertSame('<p>hi</p>', $productData['shortDescription']);
        // no media set → empty shapes
        self::assertNull($productData['image']);
        self::assertSame([], $productData['documents']);
    }

    public function testProductFamilyResolvesToARealObject(): void
    {
        $family = new ProductFamily();
        $family->setUuid('family-uuid-fe');
        $family->addTranslation(new ProductFamilyTranslation($family, 'de', 'XLR'));
        $this->entityManager->persist($family);

        $product = $this->productRepository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('de');
        $dimensionContent->setStage('draft');
        $dimensionContent->setTemplateKey('product');
        $dimensionContent->setProductFamily($family);
        $product->addDimensionContent($dimensionContent);

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($dimensionContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);
        self::assertInstanceOf(ProductFamilyWrapper::class, $productData['productFamily']);
        self::assertSame('XLR', $productData['productFamily']->getName());
    }

    public function testDetailsMediaResolvesThroughItsPropertyResolver(): void
    {
        $collection = self::createCollection();
        $media = self::createMedia($collection);
        $this->entityManager->flush();

        $product = $this->productRepository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('draft');
        $dimensionContent->setTemplateKey('product');
        // admin wire-shape, stored verbatim
        $dimensionContent->setDetailsData(['image' => ['id' => $media->getId()]]);
        $product->addDimensionContent($dimensionContent);

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($dimensionContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);

        // the wire-shape id survives to the resource loader and resolves to the real media
        $image = $productData['image'];
        self::assertInstanceOf(Media::class, $image);
        self::assertSame($media->getId(), $image->getId());
    }
}
