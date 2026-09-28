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
use Sulu\Bundle\TagBundle\Entity\Tag;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\PageTreeProductSmartContentProvider;
use Sulu\Product\Infrastructure\Sulu\Content\ProductSmartContentProvider;
use Sulu\Route\Domain\Model\Route;

#[CoversNothing]
class ProductSmartContentProviderTest extends SuluTestCase
{
    private EntityManagerInterface $entityManager;

    private ProductRepositoryInterface $productRepository;

    private ProductSmartContentProvider $provider;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get('sulu_product.product_repository');
        $this->productRepository = $productRepository;

        /** @var ProductSmartContentProvider $provider */
        $provider = $container->get('sulu_product.product_smart_content_provider');
        $this->provider = $provider;

        self::purgeDatabase();
    }

    public function testOffersEveryLiveProductWithARoute(): void
    {
        $simple = $this->createProduct(ProductInterface::TYPE_PRODUCT, 'Simple', slug: '/simple');
        $parent = $this->createProduct(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, 'Parent');
        $second = $this->createProduct(ProductInterface::TYPE_VARIANT, 'Second variant', $parent, position: 2, slug: '/second');
        $first = $this->createProduct(ProductInterface::TYPE_VARIANT, 'First variant', $parent, position: 1, slug: '/first');
        $this->createProduct(ProductInterface::TYPE_VARIANT, 'Draft variant', $parent, DimensionContentInterface::STAGE_DRAFT, slug: '/draft');
        $this->createProduct(ProductInterface::TYPE_VARIANT, 'Variant without route', $parent);
        $this->createProduct(ProductInterface::TYPE_PRODUCT, 'Simple without route');
        $this->entityManager->flush();

        $result = $this->provider->findFlatBy($this->filters(), ['title' => 'asc']);

        self::assertSame(
            [
                [$first->getUuid(), 'First variant'],
                [$second->getUuid(), 'Second variant'],
                [$simple->getUuid(), 'Simple'],
            ],
            \array_map(static fn (array $row): array => [$row['id'], $row['title']], $result),
        );
        self::assertSame(3, $this->provider->countBy($this->filters()));
    }

    public function testMatchesAVariantByTheTagsOfItsProduct(): void
    {
        $tag = new Tag();
        $tag->setName('connector');
        $this->entityManager->persist($tag);

        $this->createProduct(ProductInterface::TYPE_PRODUCT, 'Untagged', slug: '/untagged');
        $parent = $this->createProduct(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, 'Tagged parent', tags: [$tag]);
        $variant = $this->createProduct(ProductInterface::TYPE_VARIANT, 'Variant', $parent, slug: '/variant');
        $this->entityManager->flush();

        $filters = ['tags' => [$tag->getId()], 'tagOperator' => 'OR'] + $this->filters();

        self::assertSame(
            [$variant->getUuid()],
            \array_column($this->provider->findFlatBy($filters, []), 'id'),
        );
        self::assertSame(1, $this->provider->countBy($filters));
    }

    public function testPageTreeMatchesAVariantByItsOwnRoute(): void
    {
        $pageRoute = new Route('pages', 'page-uuid', 'en', '/connectors', 'sulu-io');
        $this->entityManager->persist($pageRoute);

        $parent = $this->createProduct(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, 'Parent');
        $variant = $this->createProduct(ProductInterface::TYPE_VARIANT, 'Variant', $parent, slug: '/connectors/variant', parentRoute: $pageRoute);
        $this->createProduct(ProductInterface::TYPE_VARIANT, 'Variant elsewhere', $parent, slug: '/elsewhere');
        $this->entityManager->flush();

        /** @var PageTreeProductSmartContentProvider $provider */
        $provider = self::getContainer()->get('sulu_product.page_tree_product_smart_content_provider');
        $filters = ['dataSource' => 'page-uuid'] + $this->filters();

        self::assertSame([$variant->getUuid()], \array_column($provider->findFlatBy($filters, []), 'id'));
        self::assertSame(1, $provider->countBy($filters));
    }

    /**
     * @param Tag[] $tags
     */
    private function createProduct(
        string $type,
        string $title,
        ?ProductInterface $parent = null,
        string $stage = DimensionContentInterface::STAGE_LIVE,
        array $tags = [],
        int $position = 0,
        ?string $slug = null,
        ?Route $parentRoute = null,
    ): ProductInterface {
        $product = $this->productRepository->createNew();
        $product->setType($type);
        $product->setParent($parent);
        $product->setPosition($position);

        $content = $product->createDimensionContent();
        $content->setLocale('en');
        $content->setStage($stage);
        $content->setTemplateKey('product');
        $content->setTitle($title);
        $content->setExcerptTags($tags);
        if (null !== $slug) {
            $route = new Route(ProductInterface::RESOURCE_KEY, $product->getUuid(), 'en', $slug, 'sulu-io', $parentRoute);
            $content->setRoute($route);
            $this->entityManager->persist($route);
        }
        $product->addDimensionContent($content);

        $this->productRepository->add($product);
        $this->entityManager->persist($content);

        return $product;
    }

    /**
     * @return array{
     *     categories: int[],
     *     categoryOperator: 'AND'|'OR',
     *     websiteCategories: string[],
     *     websiteCategoryOperator: 'AND'|'OR',
     *     tags: int[],
     *     tagOperator: 'AND'|'OR',
     *     websiteTags: string[],
     *     websiteTagOperator: 'AND'|'OR',
     *     locale: string,
     *     dataSource: string|null,
     *     limit: null,
     *     offset: int,
     *     includeSubFolders: bool,
     *     excludeDuplicates: bool,
     * }
     */
    private function filters(): array
    {
        return [
            'categories' => [],
            'categoryOperator' => 'OR',
            'websiteCategories' => [],
            'websiteCategoryOperator' => 'OR',
            'tags' => [],
            'tagOperator' => 'OR',
            'websiteTags' => [],
            'websiteTagOperator' => 'OR',
            'locale' => 'en',
            'dataSource' => null,
            'limit' => null,
            'offset' => 0,
            'includeSubFolders' => false,
            'excludeDuplicates' => false,
        ];
    }
}
