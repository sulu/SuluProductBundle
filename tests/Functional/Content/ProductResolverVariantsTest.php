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
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;

#[CoversClass(ProductResolver::class)]
class ProductResolverVariantsTest extends SuluTestCase
{
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

    public function testVariantsAppearUnderRootProductVariantsWithCodeAndTitle(): void
    {
        $parent = $this->productRepository->createNew();
        $parent->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $parentContent = $parent->createDimensionContent();
        $parentContent->setLocale('de');
        $parentContent->setStage('live');
        // TemplateResolver needs a registered template key to resolve the template section
        $parentContent->setTemplateKey('product');
        $parent->addDimensionContent($parentContent);

        $this->productRepository->add($parent);
        $this->entityManager->persist($parentContent);

        $variant1 = $this->productRepository->createNew();
        $variant1->setType(ProductInterface::TYPE_VARIANT);
        $variant1->setParent($parent);

        $variant1Content = $variant1->createDimensionContent();
        $variant1Content->setLocale('de');
        $variant1Content->setStage('live');
        $variant1Content->setTemplateKey('product');
        $variant1Content->setCode('NL4FX-4');
        $variant1Content->setTemplateData(['title' => 'NL4FX-4 Variant']);
        $variant1->addDimensionContent($variant1Content);

        $this->productRepository->add($variant1);
        $this->entityManager->persist($variant1Content);

        $variant2 = $this->productRepository->createNew();
        $variant2->setType(ProductInterface::TYPE_VARIANT);
        $variant2->setParent($parent);

        $variant2Content = $variant2->createDimensionContent();
        $variant2Content->setLocale('de');
        $variant2Content->setStage('live');
        $variant2Content->setTemplateKey('product');
        $variant2Content->setCode('NL4FX-5');
        $variant2->addDimensionContent($variant2Content);

        $this->productRepository->add($variant2);
        $this->entityManager->persist($variant2Content);

        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($parentContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);
        self::assertArrayHasKey('variants', $productData);

        $variants = $productData['variants'];
        self::assertIsArray($variants);
        self::assertCount(2, $variants);

        // product.variants is a list at runtime but typed `array<string, mixed>`;
        // array_values() gives PHPStan a genuine list to index into.
        $orderedVariants = \array_values($variants);
        $variant1Data = $orderedVariants[0];
        $variant2Data = $orderedVariants[1];
        self::assertIsArray($variant1Data);
        self::assertIsArray($variant2Data);

        $variant1Content = $variant1Data['content'];
        $variant2Product = $variant2Data['product'];
        $variant1Product = $variant1Data['product'];
        self::assertIsArray($variant1Content);
        self::assertIsArray($variant1Product);
        self::assertIsArray($variant2Product);

        // No property projection, so a variant keeps the page's own shape: the template data under
        // `content`, the master data under `product`. `product.variants` is a top-level root key,
        // so each resolved variant also keeps its `{resource, content, view, extension}` wrapper.
        self::assertSame('NL4FX-4', $variant1Product['code']);
        self::assertSame('NL4FX-4 Variant', $variant1Content['title']);
        self::assertSame('NL4FX-5', $variant2Product['code']);

        // Attributes resolve for a variant — the product page reads them for the selected one.
        self::assertArrayHasKey('attributes', $variant1Product);

        // A variant is not itself `product_with_variants`, so the resolve does not nest.
        self::assertArrayNotHasKey('variants', $variant1Product);
    }
}
