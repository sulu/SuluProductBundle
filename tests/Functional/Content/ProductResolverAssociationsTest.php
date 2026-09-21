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
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;

#[CoversClass(ProductResolver::class)]
class ProductResolverAssociationsTest extends SuluTestCase
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
     * Proves the resolver + DI wiring (`type => associations`) produce a `product_selection`
     * under `product.associations`, keyed by the bare type. A target below the flat `[product]`
     * path is flattened to its content bag, so the declared properties sit directly on the item
     * rather than under a `content` key.
     */
    public function testProductAssociationsAppearUnderRootProductAssociations(): void
    {
        $target = $this->publishedTarget('Alternative Target', '/alternative-target');

        $product = $this->productRepository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('draft');
        // TemplateResolver needs a registered template key to resolve the template section
        $dimensionContent->setTemplateKey('product');
        $dimensionContent->addAssociation(new ProductAssociation($dimensionContent, $target, 'alternative'));
        $product->addDimensionContent($dimensionContent);

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($dimensionContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);
        self::assertArrayHasKey('associations', $productData);
        $associationsData = $productData['associations'];
        self::assertIsArray($associationsData);

        self::assertArrayHasKey('alternative', $associationsData);
        self::assertArrayHasKey('suitable', $associationsData);
        self::assertSame([], $associationsData['suitable']);

        $alternative = $associationsData['alternative'];
        self::assertIsArray($alternative);
        self::assertCount(1, $alternative);
        $resolved = $alternative[0];
        self::assertIsArray($resolved);
        // `alternative` declares no properties of its own, so only the forced title/url remain.
        self::assertSame(['title', 'url'], \array_keys($resolved));
        self::assertSame('Alternative Target', $resolved['title']);
    }

    /**
     * A target that cannot be resolved, here one without any published content, is dropped from
     * the list instead of reaching the template as a raw ResolvableResource (sulu/sulu#9105).
     * The resolvable target next to it stays, so the drop is selective and not an empty list.
     */
    public function testAssociationWithAnUnresolvableTargetIsDropped(): void
    {
        $unpublishedTarget = $this->productRepository->createNew();
        $this->productRepository->add($unpublishedTarget);
        $publishedTarget = $this->publishedTarget('Suitable Target', '/suitable-target');

        $product = $this->productRepository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('draft');
        $dimensionContent->setTemplateKey('product');
        $dimensionContent->addAssociation(new ProductAssociation($dimensionContent, $unpublishedTarget, 'alternative'));
        $dimensionContent->addAssociation(new ProductAssociation($dimensionContent, $publishedTarget, 'suitable'));
        $product->addDimensionContent($dimensionContent);

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($dimensionContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);
        $associationsData = $productData['associations'];
        self::assertIsArray($associationsData);

        self::assertSame([], $associationsData['alternative']);

        $suitable = $associationsData['suitable'];
        self::assertIsArray($suitable);
        self::assertCount(1, $suitable);
        $resolved = $suitable[0];
        self::assertIsArray($resolved);
        self::assertSame('Suitable Target', $resolved['title'] ?? null);
    }

    /**
     * Guards that association targets are really loaded and resolved with the declared
     * property map, and that the per-type keys stay bare (`suitable`, not
     * `associations/suitable`) - the bare key is the public output contract templates read.
     */
    public function testPublishedTargetResolvesDeclaredProperties(): void
    {
        $target = $this->publishedTarget('Suitable Target', '/suitable-target', 'A very suitable product');

        $product = $this->productRepository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('en');
        $dimensionContent->setStage('draft');
        $dimensionContent->setTemplateKey('product');
        $dimensionContent->addAssociation(new ProductAssociation($dimensionContent, $target, 'suitable'));
        $product->addDimensionContent($dimensionContent);

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($dimensionContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);
        $associationsData = $productData['associations'];
        self::assertIsArray($associationsData);

        $suitable = $associationsData['suitable'];
        self::assertIsArray($suitable);
        self::assertCount(1, $suitable);

        // flat below the `[product]` path, no `content` envelope
        $resolved = $suitable[0];
        self::assertIsArray($resolved);
        self::assertSame('Suitable Target', $resolved['title'] ?? null);
        self::assertSame('A very suitable product', $resolved['description'] ?? null);
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
