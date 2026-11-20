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
use Sulu\Content\Application\ContentAggregator\ContentAggregatorInterface;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Content\Application\ResourceLoader\Loader\ResourceLoaderInterface;
use Sulu\Content\Application\ResourceLoader\ResourceLoaderProvider;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Domain\Value\ProductFamilyAttributeModeEnum;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;
use Sulu\Product\Tests\Functional\CountsSelectQueriesTrait;

/**
 * A page and a selection resolve their products with as many queries for twenty products as for
 * two: the collections the mergers read are loaded for all products at once.
 */
#[CoversClass(ProductResolver::class)]
#[CoversClass(ProductResourceLoader::class)]
class ProductResolverQueryCountTest extends SuluTestCase
{
    use CountsSelectQueriesTrait;

    private const ATTRIBUTE_KEYS = ['width', 'fabric'];

    private ContentResolverInterface $contentResolver;

    private ContentAggregatorInterface $contentAggregator;

    private EntityManagerInterface $entityManager;

    private ProductRepositoryInterface $productRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ContentResolverInterface $contentResolver */
        $contentResolver = $container->get('sulu_content.content_resolver');
        $this->contentResolver = $contentResolver;

        /** @var ContentAggregatorInterface $contentAggregator */
        $contentAggregator = $container->get('sulu_content.content_aggregator');
        $this->contentAggregator = $contentAggregator;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get('sulu_product.product_repository');
        $this->productRepository = $productRepository;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testAProductPageResolvesTwentyVariantsWithTheQueriesOfTwo(): void
    {
        [$queriesForTwo, $variantsOfTwo] = $this->resolvePageOfAProductWithVariants(2);
        [$queriesForTwenty, $variantsOfTwenty] = $this->resolvePageOfAProductWithVariants(20);

        self::assertCount(2, $variantsOfTwo);
        self::assertCount(20, $variantsOfTwenty);
        self::assertSame(
            ['title' => 'SOFA-19 Title', 'url' => null, 'code' => 'SOFA-19', 'status' => 'available', 'position' => 19],
            $variantsOfTwenty[19],
        );
        self::assertSame($queriesForTwo, $queriesForTwenty);
    }

    public function testAProductSelectionResolvesTwentyProductsWithTheQueriesOfTwo(): void
    {
        [$queriesForTwo, $selectedOfTwo] = $this->resolvePageOfAProductWithAssociations(2);
        [$queriesForTwenty, $selectedOfTwenty] = $this->resolvePageOfAProductWithAssociations(20);

        self::assertCount(2, $selectedOfTwo);
        self::assertCount(20, $selectedOfTwenty);
        self::assertSame($queriesForTwo, $queriesForTwenty);
    }

    /** The loader loads what the mergers read, so the stored collections merge without a query. */
    public function testLoadedProductsMergeTheirCollectionsWithoutFurtherQueries(): void
    {
        self::purgeDatabase();
        $family = $this->createFamily('sofa', $this->createAttributes());
        $target = $this->createProduct(ProductInterface::TYPE_PRODUCT, 'TARGET', $family);
        $parent = $this->createProduct(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, 'SOFA', $family);
        $uuids = [];
        foreach ([0, 1] as $position) {
            $uuids[] = $this->createProduct(ProductInterface::TYPE_VARIANT, 'SOFA-' . $position, $family, $parent, $position, $target)->getUuid();
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->getProductResourceLoader()->load($uuids, 'de', ['properties' => ['title' => 'product.title']]);
        $queriesBefore = $this->countSelectQueries();

        $merged = [];
        foreach ($loaded as $product) {
            self::assertInstanceOf(ProductInterface::class, $product);
            $content = $this->contentAggregator->aggregate($product, ['locale' => 'de', 'stage' => 'live']);

            $attributes = [];
            foreach ($content->getAttributes() as $value) {
                $attributes[$value->getAttributeKey()] = $value->getText();
            }
            \ksort($attributes);

            $merged[$content->getCode()] = [
                'attributes' => $attributes,
                'associations' => \array_map(static fn ($association) => $association->getTarget()->getUuid(), $content->getAssociations()),
                'additionalWebspaces' => $content->getAdditionalWebspaces(),
            ];
        }

        \ksort($merged);
        self::assertSame([
            'SOFA-0' => [
                'attributes' => ['fabric' => 'SOFA-0 fabric', 'width' => 'SOFA-0 width'],
                'associations' => [$target->getUuid()],
                'additionalWebspaces' => ['sulu-io'],
            ],
            'SOFA-1' => [
                'attributes' => ['fabric' => 'SOFA-1 fabric', 'width' => 'SOFA-1 width'],
                'associations' => [$target->getUuid()],
                'additionalWebspaces' => ['sulu-io'],
            ],
        ], $merged);
        self::assertSame($queriesBefore, $this->countSelectQueries(), 'merging must not query');
    }

    /** The labels, group and options of the attributes are only loaded for a list that asks for `product.attributes`. */
    public function testTheLoaderLoadsTheAttributeLabelsOnlyWhenTheListRequestsThem(): void
    {
        self::purgeDatabase();
        $family = $this->createFamily('sofa', $this->createAttributes());
        $uuid = $this->createProduct(ProductInterface::TYPE_PRODUCT, 'SOFA', $family)->getUuid();
        $this->entityManager->flush();

        $queriesWithoutAttributes = $this->countQueriesLoading($uuid, ['title' => 'product.title']);
        $queriesWithAttributes = $this->countQueriesLoading($uuid, ['title' => 'product.title', 'attributes' => 'product.attributes']);

        self::assertSame($queriesWithoutAttributes + 2, $queriesWithAttributes, 'the attribute labels, groups and options, which also load the attributes');

        $queriesBefore = $this->countSelectQueries();
        $product = $this->entityManager->find(ProductInterface::class, $uuid);
        self::assertInstanceOf(ProductInterface::class, $product);
        $labels = [];
        foreach ($product->getDimensionContents() as $dimensionContent) {
            foreach ($dimensionContent->getAttributes() as $value) {
                $attribute = $value->getAttribute();
                $labels[$attribute->getKey()] = [$attribute->getTranslation('de')?->getName(), $attribute->getGroup()->getTranslation('de')?->getName()];
            }
        }

        \ksort($labels);
        self::assertSame(['fabric' => ['Bezug', 'Maße'], 'width' => ['Breite', 'Maße']], $labels);
        self::assertSame($queriesBefore, $this->countSelectQueries(), 'a list with `product.attributes` loads the labels with the product');
    }

    /**
     * @param array<string, string> $properties
     */
    private function countQueriesLoading(string $uuid, array $properties): int
    {
        $this->entityManager->clear();
        $queriesBefore = $this->countSelectQueries();

        $this->getProductResourceLoader()->load([$uuid], 'de', ['properties' => $properties]);

        return $this->countSelectQueries() - $queriesBefore;
    }

    /**
     * @return array{int, mixed[]} the queries and the resolved `product.variants`
     */
    private function resolvePageOfAProductWithVariants(int $count): array
    {
        self::purgeDatabase();
        $family = $this->createFamily('sofa', $this->createAttributes());
        $parent = $this->createProduct(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, 'SOFA', $family);
        for ($position = 0; $position < $count; ++$position) {
            $this->createProduct(ProductInterface::TYPE_VARIANT, 'SOFA-' . $position, $family, $parent, $position);
        }
        $this->entityManager->flush();

        [$queries, $productData] = $this->resolvePage($parent->getUuid());
        $variants = $productData['variants'] ?? null;
        self::assertIsArray($variants);

        return [$queries, $variants];
    }

    /**
     * @return array{int, mixed[]} the queries and the resolved `suitable` association
     */
    private function resolvePageOfAProductWithAssociations(int $count): array
    {
        self::purgeDatabase();
        $attributes = $this->createAttributes();
        $targets = [];
        for ($index = 0; $index < $count; ++$index) {
            // a family each, as in a selection across the catalog
            $targets[] = $this->createProduct(ProductInterface::TYPE_PRODUCT, 'TARGET-' . $index, $this->createFamily('family-' . $index, $attributes));
        }
        $product = $this->createProduct(ProductInterface::TYPE_PRODUCT, 'SOFA', $this->createFamily('sofa', $attributes), null, 0, ...$targets);
        $this->entityManager->flush();

        [$queries, $productData] = $this->resolvePage($product->getUuid());
        $associations = $productData['associations'] ?? null;
        self::assertIsArray($associations);
        $suitable = $associations['suitable'] ?? null;
        self::assertIsArray($suitable);

        return [$queries, $suitable];
    }

    /**
     * Resolves the product as its page does, from an empty entity manager.
     *
     * @return array{int, array<string, mixed>} the queries and the `product` namespace
     */
    private function resolvePage(string $uuid): array
    {
        $this->entityManager->clear();
        $queriesBefore = $this->countSelectQueries();

        $product = $this->productRepository->getOneBy(
            ['uuid' => $uuid, 'locale' => 'de', 'stage' => 'live'],
            [ProductRepositoryInterface::GROUP_SELECT_PRODUCT_WEBSITE => true],
        );
        $result = $this->contentResolver->resolve($this->contentAggregator->aggregate($product, ['locale' => 'de', 'stage' => 'live']));

        $productData = $result['product'] ?? null;
        self::assertIsArray($productData);

        /** @var array<string, mixed> $productData */
        return [$this->countSelectQueries() - $queriesBefore, $productData];
    }

    /**
     * @return list<AttributeInterface>
     */
    private function createAttributes(): array
    {
        $container = self::getContainer();
        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);

        $group = $groupRepository->createNew();
        $group->addTranslation(new AttributeGroupTranslation($group, 'de', 'Maße'));
        $groupRepository->add($group);

        $attributes = [];
        foreach (\array_combine(self::ATTRIBUTE_KEYS, ['Breite', 'Bezug']) as $key => $name) {
            $attribute = $attributeRepository->createNew($group);
            $attribute->setKey($key);
            $attribute->setType(AttributeInterface::TYPE_TEXT);
            $attribute->addTranslation(new AttributeTranslation($attribute, 'de', $name));
            $attributeRepository->add($attribute);
            $attributes[] = $attribute;
        }

        return $attributes;
    }

    /**
     * @param list<AttributeInterface> $attributes
     */
    private function createFamily(string $key, array $attributes): ProductFamilyInterface
    {
        /** @var ProductFamilyRepositoryInterface $familyRepository */
        $familyRepository = self::getContainer()->get(ProductFamilyRepositoryInterface::class);

        $family = $familyRepository->createNew();
        $family->setKey($key);
        $family->addTranslation(new ProductFamilyTranslation($family, 'de', \ucfirst($key)));

        foreach ($attributes as $attribute) {
            $familyAttribute = new ProductFamilyAttribute($family, $attribute);
            $familyAttribute->setMode(ProductFamilyAttributeModeEnum::VARIANT_SPECIFIC);
            $family->addFamilyAttribute($familyAttribute);
        }

        $familyRepository->add($family);

        return $family;
    }

    /**
     * A published product as the admin stores it: the shared data in the unlocalized content, the
     * title and the webspaces in the German one.
     */
    private function createProduct(
        string $type,
        string $code,
        ProductFamilyInterface $family,
        ?ProductInterface $parent = null,
        int $position = 0,
        ProductInterface ...$associationTargets,
    ): ProductInterface {
        $product = $this->productRepository->createNew();
        $product->setType($type);
        $product->setPosition($position);
        if (null !== $parent) {
            $product->setParent($parent);
        }

        $unlocalized = $product->createDimensionContent();
        $unlocalized->setStage('live');
        $unlocalized->setCode($code);
        $unlocalized->setProductFamily($family);
        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            $attribute = $familyAttribute->getAttribute();
            $value = new ProductAttributeValue($unlocalized, $attribute, $attribute->getKey());
            $value->setText($code . ' ' . $attribute->getKey());
            $value->setProductFamilyAttribute($familyAttribute);
            $unlocalized->addAttribute($value);
        }
        foreach ($associationTargets as $target) {
            $unlocalized->addAssociation(new ProductAssociation($unlocalized, $target, 'suitable'));
        }
        $product->addDimensionContent($unlocalized);

        $localized = $product->createDimensionContent();
        $localized->setLocale('de');
        $localized->setStage('live');
        // TemplateResolver needs a registered template key to resolve the template section
        $localized->setTemplateKey('product');
        $localized->setTemplateData(['title' => $code . ' Title']);
        $localized->setAdditionalWebspaces(['sulu-io']);
        $product->addDimensionContent($localized);

        $this->productRepository->add($product);
        $this->entityManager->persist($unlocalized);
        $this->entityManager->persist($localized);

        return $product;
    }

    private function getProductResourceLoader(): ResourceLoaderInterface
    {
        /** @var ResourceLoaderProvider $loaders */
        $loaders = self::getContainer()->get('sulu_content.resource_loader_provider');
        $loader = $loaders->getResourceLoader(ProductResourceLoader::getKey());
        self::assertNotNull($loader);

        return $loader;
    }
}
