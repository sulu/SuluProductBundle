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
use Sulu\Product\Domain\Model\ProductComponent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;

#[CoversClass(ProductResolver::class)]
class ProductResolverComponentsTest extends SuluTestCase
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

    /**
     * Proves the resolver wiring: `product.components` lists the component products in position
     * order, resolved like an association target without project params, and drops one that cannot
     * be resolved.
     */
    public function testComponentsAppearUnderRootProductComponentsInPositionOrder(): void
    {
        $first = $this->publishedTarget('First Component', '/first-component');
        $second = $this->publishedTarget('Second Component', '/second-component');
        $unpublished = $this->productRepository->createNew();
        $this->productRepository->add($unpublished);

        $product = $this->productRepository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('draft');
        $dimensionContent->setTemplateKey('product');
        $dimensionContent->addComponent(new ProductComponent($dimensionContent, $second, 1));
        $dimensionContent->addComponent(new ProductComponent($dimensionContent, $unpublished, 2));
        $dimensionContent->addComponent(new ProductComponent($dimensionContent, $first, 0));
        $product->addDimensionContent($dimensionContent);

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($dimensionContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);
        self::assertArrayHasKey('components', $productData);
        $components = $productData['components'];
        self::assertIsArray($components);

        self::assertCount(2, $components);
        self::assertIsArray($components[0]);
        self::assertSame(['title', 'url'], \array_keys($components[0]));
        self::assertSame('First Component', $components[0]['title']);
        self::assertIsArray($components[1]);
        self::assertSame('Second Component', $components[1]['title']);
    }

    /**
     * A target with a published English content, the minimum for the association to resolve.
     */
    private function publishedTarget(string $title, string $url, ?string $description = null): ProductInterface
    {
        $target = $this->productRepository->createNew();

        $unlocalizedLive = $target->createDimensionContent();
        $unlocalizedLive->setStage('live');
        $target->addDimensionContent($unlocalizedLive);

        $localizedLive = $target->createDimensionContent();
        $localizedLive->setLocale('en');
        $localizedLive->setStage('live');
        $localizedLive->setTemplateKey('product');
        $localizedLive->setTemplateData(\array_filter([
            'title' => $title,
            'url' => $url,
            'description' => $description,
        ], static fn (?string $value): bool => null !== $value));
        $target->addDimensionContent($localizedLive);

        $this->productRepository->add($target);
        $this->entityManager->persist($unlocalizedLive);
        $this->entityManager->persist($localizedLive);

        return $target;
    }
}
