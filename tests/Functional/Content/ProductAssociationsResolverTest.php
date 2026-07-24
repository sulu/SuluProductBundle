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
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductAssociationsResolver;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;

#[CoversClass(ProductAssociationsResolver::class)]
class ProductAssociationsResolverTest extends SuluTestCase
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
     * The associated target product is intentionally left without a "live" dimension content:
     * fully resolving it recursively (title/url) would require route generation/publishing
     * setup unrelated to this resolver. Asserting on the still-unresolved ResolvableResource
     * is sufficient to prove the resolver + DI wiring (`type => associations`) produce a
     * `product_selection`-shaped resolvable, limited to `title`/`url`, under `extension.associations`.
     */
    public function testProductAssociationsAppearUnderExtensionAssociations(): void
    {
        $target = $this->productRepository->createNew();
        $this->productRepository->add($target);

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

        self::assertArrayHasKey('associations', $result['extension']);
        $associationsData = $result['extension']['associations'];

        self::assertArrayHasKey('alternative', $associationsData);
        self::assertArrayHasKey('suitable', $associationsData);
        self::assertSame([], $associationsData['suitable']);

        $alternative = $associationsData['alternative'];
        self::assertIsArray($alternative);
        self::assertCount(1, $alternative);
        $resolvable = $alternative[0];
        self::assertInstanceOf(ResolvableResource::class, $resolvable);
        self::assertSame($target->getUuid(), $resolvable->getId());
        self::assertSame(ProductResourceLoader::getKey(), $resolvable->getResourceLoaderKey());
        self::assertSame(ProductInterface::RESOURCE_KEY, $resolvable->getResourceKey());
        self::assertSame(100, $resolvable->getPriority());
        self::assertSame(['title' => 'title', 'url' => 'url'], $resolvable->getMetadata()['properties'] ?? null);
    }
}
