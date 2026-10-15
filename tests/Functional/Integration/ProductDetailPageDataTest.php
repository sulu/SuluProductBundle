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

namespace Sulu\Product\Tests\Functional\Integration;

use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\CategoryBundle\Entity\CategoryTranslation;
use Sulu\Bundle\TagBundle\Entity\Tag;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeGroupTranslationInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeOptionTranslationInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\AttributeTranslationInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * The product detail page of the default template shows the excerpt, the seo data and the attributes
 * with their groups and options. The attribute select loads the attribute part of that in a fixed
 * number of queries, so these tests pin that nothing the page shows is lost on the way, and that the
 * number of queries stays the same however many attributes a product has.
 */
#[CoversNothing]
class ProductDetailPageDataTest extends SuluTestCase
{
    private const VALUES = ['colour' => 'red', 'finish' => 'matte', 'weight' => 2.5, 'note' => 'Hand made', 'waterproof' => true];

    private KernelBrowser $admin;

    /** @var array<string, string> */
    private array $attributeIds = [];

    private string $familyId;

    private int $categoryId;

    /** @var list<int> */
    private array $tagIds = [];

    protected function setUp(): void
    {
        self::purgeDatabase();
        self::ensureKernelShutdown();

        $this->admin = self::createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    /**
     * Not kept from setUp: a request of the client boots a new kernel, whose entity manager starts without
     * the entities of the request before.
     */
    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    public function testThePageShowsEverythingTheProductHolds(): void
    {
        $this->createCatalog();
        $this->createProduct('/shirt', 'Shirt', self::VALUES);

        $this->assertShirtPage($this->requestWebsite('http://sulu.io/en/shirt'), [
            'title' => 'Shirt',
            'excerptTitle' => 'Shirt excerpt',
            'categories' => ['Clothing'],
            'seoTitle' => 'Shirt seo',
            'groups' => [
                'Appearance' => ['Colour: Red', 'Finish: Matte'],
                'Technical data' => ['Weight: 2.5', 'Note: Hand made', 'Waterproof: Yes'],
            ],
        ]);
    }

    /**
     * Title, excerpt, seo and the labels of attributes, options and groups come in the requested locale.
     */
    public function testThePageShowsTheDataOfTheRequestedLocale(): void
    {
        $this->createCatalog();
        $this->createProduct('/shirt', 'Shirt', self::VALUES);

        $this->assertShirtPage($this->requestWebsite('http://sulu.io/de/hemd'), [
            'title' => 'Hemd',
            'excerptTitle' => 'Hemd excerpt',
            'categories' => ['Kleidung'],
            'seoTitle' => 'Hemd seo',
            'groups' => [
                'Aussehen' => ['Farbe: Rot', 'Oberfläche: Matt'],
                'Technische Daten' => ['Gewicht: 2.5', 'Notiz: Handgemacht', 'Wasserdicht: Yes'],
            ],
        ]);
    }

    /**
     * The variant page renders its product, so the product's excerpt and seo show, with the variant's own
     * value next to the product's.
     */
    public function testAVariantPageShowsTheDataOfTheVariantAndItsProduct(): void
    {
        $this->createCatalog();
        $productId = $this->createProduct('/shirt', 'Shirt', ['finish' => 'matte', 'weight' => 2.5], ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $this->createVariant($productId, '/shirt-blue', ['colour' => 'blue']);

        $this->assertShirtPage($this->requestWebsite('http://sulu.io/en/shirt-blue'), [
            'title' => 'Shirt',
            'excerptTitle' => 'Shirt excerpt',
            'categories' => ['Clothing'],
            'seoTitle' => 'Shirt seo',
            'groups' => [
                'Appearance' => ['Colour: Blue', 'Finish: Matte'],
                'Technical data' => ['Weight: 2.5'],
            ],
        ]);

        $this->assertShirtPage($this->requestWebsite('http://sulu.io/de/hemd-blau'), [
            'title' => 'Hemd',
            'excerptTitle' => 'Hemd excerpt',
            'categories' => ['Kleidung'],
            'seoTitle' => 'Hemd seo',
            'groups' => [
                'Aussehen' => ['Farbe: Blau', 'Oberfläche: Matt'],
                'Technische Daten' => ['Gewicht: 2.5'],
            ],
        ]);
    }

    /**
     * The select adds queries to the load and takes the lazy ones away: what the page reads of the
     * product is the same, whether the attribute graph is loaded by the select or lazily.
     */
    #[DataProvider('provideLocales')]
    public function testTheSelectLoadsTheSameDataAsTheLazyLoad(string $locale): void
    {
        $this->createCatalog();
        $productId = $this->createProduct('/shirt', 'Shirt', self::VALUES);

        $lazy = $this->snapshot($this->loadProduct($productId, false, $locale));
        $withSelect = $this->snapshot($this->loadProduct($productId, true, $locale));

        // a snapshot that holds nothing would prove nothing
        self::assertNotEmpty($lazy['dimensionContents']);
        self::assertSame(['colour', 'finish', 'note', 'waterproof', 'weight'], $this->attributeKeys($withSelect));
        self::assertSame(['Cotton', 'Summer'], $this->excerptTags($withSelect));
        self::assertSame($lazy, $withSelect);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideLocales(): iterable
    {
        yield 'english' => ['en'];
        yield 'german' => ['de'];
    }

    /**
     * Everything the page reads of the attributes is in memory after the load: the collections are
     * initialized and walking them runs no query.
     */
    #[DataProvider('provideLocales')]
    public function testTheSelectLeavesNothingToLoadLazily(string $locale): void
    {
        $this->createCatalog();
        $productId = $this->createProduct('/shirt', 'Shirt', self::VALUES);

        $product = $this->loadProduct($productId, true, $locale);

        $values = 0;
        foreach ($this->dimensionContents($product) as $dimensionContent) {
            $collection = $dimensionContent->getAttributes();
            self::assertInstanceOf(PersistentCollection::class, $collection);
            self::assertTrue($collection->isInitialized(), 'The values of a dimension content are loaded.');

            foreach ($dimensionContent->getAttributes() as $value) {
                ++$values;
                $attribute = $value->getAttribute();
                self::assertFalse($this->entityManager()->getUnitOfWork()->isUninitializedObject($attribute), 'The attribute of a value is loaded.');
                $this->assertInitialized($attribute, 'translations', 'The translations of attribute "' . $attribute->getKey() . '" are loaded.');
                $this->assertInitialized($attribute, 'options', 'The options of attribute "' . $attribute->getKey() . '" are loaded.');

                $group = $attribute->getGroup();
                self::assertFalse($this->entityManager()->getUnitOfWork()->isUninitializedObject($group), 'The group of an attribute is loaded.');
                $this->assertInitialized($group, 'translations', 'The translations of the group are loaded.');

                foreach ($attribute->getOptions() as $option) {
                    $this->assertInitialized($option, 'translations', 'The translations of option "' . $option->getKey() . '" are loaded.');
                }
            }
        }
        self::assertGreaterThan(0, $values, 'A product without values would prove nothing.');

        $walk = function(ProductInterface $product): int {
            return $this->countQueries(function() use ($product): void {
                foreach ($this->dimensionContents($product) as $dimensionContent) {
                    $this->attributeGraph($dimensionContent);
                }
            });
        };

        self::assertSame(0, $walk($product), 'Reading the attribute graph of the loaded product runs queries.');
        self::assertGreaterThan(0, $walk($this->loadProduct($productId, false, $locale)), 'The lazy load is the control: its walk has to run queries.');
    }

    /**
     * The queries of the page do not depend on how many attributes the product has: the values, the
     * attributes with their labels and options, the groups. A new attribute per page that costs a
     * query shows here.
     */
    public function testTheQueriesOfThePageDoNotGrowWithTheAttributes(): void
    {
        $this->createCatalog();
        $this->createProduct('/small', 'Small', ['colour' => 'red']);
        $this->createProduct('/large', 'Large', self::VALUES);

        $small = $this->pageQueries('http://sulu.io/en/small');
        $large = $this->pageQueries('http://sulu.io/en/large');

        $this->assertSameQueryCount($small, $large);
        $this->assertAttributeQueries(4, $small);
        $this->assertAttributeQueries(4, $large);
    }

    /**
     * A variant page loads the product and the variant, each with the select.
     */
    public function testTheQueriesOfAVariantPageDoNotGrowWithTheAttributes(): void
    {
        $this->createCatalog();
        $small = $this->createProduct('/small', 'Small', ['finish' => 'matte'], ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $this->createVariant($small, '/small-red', ['colour' => 'red']);
        $large = $this->createProduct('/large', 'Large', ['finish' => 'matte', 'weight' => 2.5, 'note' => 'Hand made', 'waterproof' => true], ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);
        $this->createVariant($large, '/large-blue', ['colour' => 'blue']);

        $smallQueries = $this->pageQueries('http://sulu.io/en/small-red');
        $largeQueries = $this->pageQueries('http://sulu.io/en/large-blue');

        $this->assertSameQueryCount($smallQueries, $largeQueries);
        $this->assertAttributeQueries(8, $smallQueries);
        $this->assertAttributeQueries(8, $largeQueries);
    }

    /**
     * @param list<string> $one
     * @param list<string> $other
     */
    private function assertSameQueryCount(array $one, array $other): void
    {
        self::assertNotEmpty($one, 'A page without queries would prove nothing.');
        self::assertSameSize(
            $one,
            $other,
            "A page with more attributes ran more queries.\n\nfewer:\n" . \implode("\n", $one) . "\n\nmore:\n" . \implode("\n", $other),
        );
    }

    /**
     * The values with their attributes, the attributes with their labels, the options and the groups,
     * once per loaded product.
     *
     * @param list<string> $queries
     */
    private function assertAttributeQueries(int $expected, array $queries): void
    {
        $attributeQueries = \array_values(\array_filter($queries, static fn (string $sql): bool => 1 === \preg_match('/\bpr_(attribute|product_attribute_values)/', $sql)));

        self::assertCount($expected, $attributeQueries, 'The attributes of the page take ' . $expected . " queries:\n" . \implode("\n", $attributeQueries));
    }

    /**
     * @param array{title: string, excerptTitle: string, categories: list<string>, seoTitle: string, groups: array<string, list<string>>} $expected
     */
    private function assertShirtPage(Crawler $crawler, array $expected): void
    {
        self::assertSame($expected['title'], $crawler->filter('h1')->text());
        self::assertStringContainsString($expected['title'] . ' description', $crawler->filter('.description')->text());

        self::assertSame($expected['excerptTitle'], $crawler->filter('.excerpt-title')->text());
        self::assertSame('Excerpt description', $crawler->filter('.excerpt-description')->text());
        self::assertSame(['Cotton', 'Summer'], $this->texts($crawler, '.excerpt-tags li'));
        self::assertSame($expected['categories'], $this->texts($crawler, '.excerpt-categories li'));

        self::assertSame($expected['seoTitle'], $crawler->filter('title')->text());
        self::assertSame('Seo description', $crawler->filter('meta[name="description"]')->attr('content'));
        self::assertSame('shirt, cotton', $crawler->filter('meta[name="keywords"]')->attr('content'));

        self::assertEquals($expected['groups'], $this->attributeGroups($crawler));
    }

    /**
     * @return list<string> the SQL of the queries the request ran
     */
    private function pageQueries(string $url): array
    {
        self::ensureKernelShutdown();
        $client = self::createWebsiteClient();
        $client->enableProfiler();
        $client->request('GET', $url);
        $this->assertHttpStatusCode(200, $client->getResponse());

        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile, 'The profiler is not enabled in the test application.');
        $getQueries = [$profile->getCollector('db'), 'getQueries'];
        self::assertIsCallable($getQueries);

        /** @var array<string, list<array{sql: string}>> $queriesByConnection */
        $queriesByConnection = $getQueries();

        $sql = [];
        foreach ($queriesByConnection as $queries) {
            foreach ($queries as $query) {
                $sql[] = $query['sql'];
            }
        }

        return $sql;
    }

    private function loadProduct(string $uuid, bool $withSelect, string $locale = 'en'): ProductInterface
    {
        /** @var ProductRepositoryInterface $repository */
        $repository = self::getContainer()->get('sulu_product.product_repository');
        $dimensionAttributes = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'version' => DimensionContentInterface::CURRENT_VERSION,
        ];

        $selects = [
            ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                'dimensionAttributes' => $dimensionAttributes,
                'selects' => [
                    DimensionContentQueryEnhancer::SELECT_EXCERPT_TAGS => true,
                    DimensionContentQueryEnhancer::SELECT_EXCERPT_CATEGORIES => true,
                    DimensionContentQueryEnhancer::SELECT_EXCERPT_CATEGORIES_TRANSLATION => true,
                ],
            ],
        ];
        if ($withSelect) {
            $selects[ProductRepositoryInterface::SELECT_PRODUCT_ATTRIBUTES] = true;
        }

        $this->entityManager()->clear();

        return $repository->getOneBy(['uuid' => $uuid], $selects);
    }

    /**
     * @return list<ProductDimensionContentInterface>
     */
    private function dimensionContents(ProductInterface $product): array
    {
        /** @var list<ProductDimensionContentInterface> $dimensionContents */
        $dimensionContents = \iterator_to_array($product->getDimensionContents(), false);

        return $dimensionContents;
    }

    /**
     * Everything a template can read of the product, as plain data. Reading it loads what is not
     * loaded yet, so two loads that differ in what they preload must give the same snapshot.
     *
     * @return array{uuid: string, type: string, dimensionContents: list<array{
     *     stage: string,
     *     locale: string|null,
     *     version: int,
     *     template: string|null,
     *     templateData: array<string, mixed>,
     *     excerpt: array<string, mixed>,
     *     excerptTags: list<string>,
     *     excerptCategories: list<string>,
     *     seo: array<string, mixed>,
     *     route: string|null,
     *     workflowPlace: string|null,
     *     workflowPublished: string|null,
     *     authored: string|null,
     *     lastModified: string|null,
     *     attributes: array<string, array<string, mixed>>,
     * }>}
     */
    private function snapshot(ProductInterface $product): array
    {
        $dimensionContents = [];
        foreach ($this->dimensionContents($product) as $dimensionContent) {
            $dimensionContents[] = [
                'stage' => $dimensionContent->getStage(),
                'locale' => $dimensionContent->getLocale(),
                'version' => $dimensionContent->getVersion(),
                'template' => $dimensionContent->getTemplateKey(),
                'templateData' => $dimensionContent->getTemplateData(),
                'excerpt' => $dimensionContent->getExcerptData(),
                'excerptTags' => \array_map(static fn ($tag): string => $tag->getName(), \iterator_to_array($dimensionContent->getExcerptTags(), false)),
                'excerptCategories' => \array_map(static fn ($category): string => $category->getKey() ?? '', \iterator_to_array($dimensionContent->getExcerptCategories(), false)),
                'seo' => $dimensionContent->getSeoData(),
                'route' => $dimensionContent->getRoute()?->getSlug(),
                'workflowPlace' => $dimensionContent->getWorkflowPlace(),
                'workflowPublished' => $dimensionContent->getWorkflowPublished()?->format(\DATE_ATOM),
                'authored' => $dimensionContent->getAuthored()?->format(\DATE_ATOM),
                'lastModified' => $dimensionContent->getLastModified()?->format(\DATE_ATOM),
                'attributes' => $this->attributeGraph($dimensionContent),
            ];
        }

        \usort($dimensionContents, static fn (array $a, array $b): int => [$a['stage'], $a['locale'] ?? ''] <=> [$b['stage'], $b['locale'] ?? '']);

        return ['uuid' => $product->getUuid(), 'type' => $product->getType(), 'dimensionContents' => $dimensionContents];
    }

    /**
     * @return array<string, array<string, mixed>> by attribute key
     */
    private function attributeGraph(ProductDimensionContentInterface $dimensionContent): array
    {
        $graph = [];
        foreach ($dimensionContent->getAttributes() as $value) {
            $attribute = $value->getAttribute();
            $group = $attribute->getGroup();

            $options = [];
            foreach ($attribute->getOptions() as $option) {
                $options[$option->getKey()] = [
                    'position' => $option->getPosition(),
                    'names' => $this->names($option->getTranslations()),
                ];
            }
            \ksort($options);

            $graph[$attribute->getKey()] = [
                'value' => $value->getValue(),
                'text' => $value->getText(),
                'number' => $value->getNumber(),
                'optionKey' => $value->getAttributeOptionKey(),
                'option' => $value->getAttributeOption()?->getKey(),
                'optionNames' => null === $value->getAttributeOption() ? null : $this->names($value->getAttributeOption()->getTranslations()),
                'attribute' => [
                    'uuid' => $attribute->getUuid(),
                    'type' => $attribute->getType(),
                    'config' => $attribute->getConfig(),
                    'localized' => $attribute->isLocalized(),
                    'filterable' => $attribute->isFilterable(),
                    'position' => $attribute->getPosition(),
                    'defaultLocale' => $attribute->getDefaultLocale(),
                    'names' => $this->names($attribute->getTranslations()),
                    'options' => $options,
                ],
                'group' => [
                    'uuid' => $group->getUuid(),
                    'created' => $group->getCreated()->format(\DATE_ATOM),
                    'names' => $this->names($group->getTranslations()),
                ],
            ];
        }
        \ksort($graph);

        return $graph;
    }

    /**
     * @param iterable<AttributeTranslationInterface|AttributeOptionTranslationInterface|AttributeGroupTranslationInterface> $translations
     *
     * @return array<string, string>
     */
    private function names(iterable $translations): array
    {
        $names = [];
        foreach ($translations as $translation) {
            $names[$translation->getLocale()] = $translation->getName();
        }
        \ksort($names);

        return $names;
    }

    /**
     * @param array{dimensionContents: list<array{attributes: array<string, array<string, mixed>>}>} $snapshot
     *
     * @return list<string>
     */
    private function attributeKeys(array $snapshot): array
    {
        $keys = [];
        foreach ($snapshot['dimensionContents'] as $dimensionContent) {
            foreach (\array_keys($dimensionContent['attributes']) as $key) {
                $keys[$key] = (string) $key;
            }
        }
        $keys = \array_values($keys);
        \sort($keys);

        return $keys;
    }

    /**
     * @param array{dimensionContents: list<array{excerptTags: list<string>}>} $snapshot
     *
     * @return list<string>
     */
    private function excerptTags(array $snapshot): array
    {
        $tags = [];
        foreach ($snapshot['dimensionContents'] as $dimensionContent) {
            foreach ($dimensionContent['excerptTags'] as $tag) {
                $tags[$tag] = $tag;
            }
        }
        $tags = \array_values($tags);
        \sort($tags);

        return $tags;
    }

    private function assertInitialized(object $entity, string $property, string $message): void
    {
        $collection = (new \ReflectionProperty($entity, $property))->getValue($entity);

        self::assertInstanceOf(PersistentCollection::class, $collection, $message);
        self::assertTrue($collection->isInitialized(), $message);
    }

    /**
     * @param callable(): void $callback
     */
    private function countQueries(callable $callback): int
    {
        /** @var BacktraceDebugDataHolder $debugDataHolder */
        $debugDataHolder = self::getContainer()->get('doctrine.debug_data_holder');
        $debugDataHolder->reset();

        $callback();

        return \count($debugDataHolder->getData()['default'] ?? []);
    }

    /**
     * @return array<string, list<string>> the attribute lines by group label
     */
    private function attributeGroups(Crawler $crawler): array
    {
        $groups = [];
        $crawler->filter('.attribute-group')->each(static function(Crawler $group) use (&$groups): void {
            $groups[$group->filter('h2')->text()] = $group->filter('li')->each(static fn (Crawler $item): string => $item->text());
        });

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function texts(Crawler $crawler, string $selector): array
    {
        return $crawler->filter($selector)->each(static fn (Crawler $node): string => $node->text());
    }

    private function requestWebsite(string $url): Crawler
    {
        self::ensureKernelShutdown();

        $client = self::createWebsiteClient();
        $crawler = $client->request('GET', $url);

        $this->assertHttpStatusCode(200, $client->getResponse());

        return $crawler;
    }

    /**
     * Two groups with options (two attributes), number, localized text and boolean attributes, each with an
     * english and a german label.
     */
    private function createCatalog(): void
    {
        $container = self::getContainer();
        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);

        $appearance = $groupRepository->createNew();
        $appearance->addTranslation(new AttributeGroupTranslation($appearance, 'en', 'Appearance'));
        $appearance->addTranslation(new AttributeGroupTranslation($appearance, 'de', 'Aussehen'));
        $groupRepository->save($appearance);

        $technical = $groupRepository->createNew();
        $technical->addTranslation(new AttributeGroupTranslation($technical, 'en', 'Technical data'));
        $technical->addTranslation(new AttributeGroupTranslation($technical, 'de', 'Technische Daten'));
        $groupRepository->save($technical);

        $definitions = [
            ['colour', $appearance, AttributeInterface::TYPE_OPTIONS, false, ['Colour', 'Farbe'], ['red' => ['Red', 'Rot'], 'blue' => ['Blue', 'Blau'], 'green' => ['Green', 'Grün']]],
            ['finish', $appearance, AttributeInterface::TYPE_OPTIONS, false, ['Finish', 'Oberfläche'], ['matte' => ['Matte', 'Matt'], 'glossy' => ['Glossy', 'Glänzend']]],
            ['weight', $technical, AttributeInterface::TYPE_NUMBER, false, ['Weight', 'Gewicht'], []],
            ['note', $technical, AttributeInterface::TYPE_TEXT, true, ['Note', 'Notiz'], []],
            ['waterproof', $technical, AttributeInterface::TYPE_BOOLEAN, false, ['Waterproof', 'Wasserdicht'], []],
        ];

        $familyAttributes = [];
        foreach ($definitions as $position => [$key, $group, $type, $localized, [$en, $de], $options]) {
            $attribute = $attributeRepository->createNew($group);
            $attribute->setKey($key);
            $attribute->setType($type);
            $attribute->setLocalized($localized);
            $attribute->setPosition($position);
            $attribute->addTranslation(new AttributeTranslation($attribute, 'en', $en));
            $attribute->addTranslation(new AttributeTranslation($attribute, 'de', $de));
            foreach ($options as $optionKey => [$optionEn, $optionDe]) {
                $option = new AttributeOption($attribute, $optionKey);
                $option->addTranslation(new AttributeOptionTranslation($option, 'en', $optionEn));
                $option->addTranslation(new AttributeOptionTranslation($option, 'de', $optionDe));
                $attribute->addOption($option);
            }
            $attributeRepository->save($attribute);

            $this->attributeIds[$key] = $attribute->getUuid();
            $familyAttributes[] = ['id' => $attribute->getUuid(), 'required' => false, 'variantSpecific' => 'colour' === $key];
        }

        $this->entityManager()->flush();

        $this->admin->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Clothing',
            'key' => 'clothing',
            'attributes' => $familyAttributes,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->admin->getResponse());
        $this->familyId = $this->responseId();

        $category = new Category();
        $category->setKey('clothing');
        $category->setDefaultLocale('en');
        $translation = new CategoryTranslation();
        $translation->setLocale('en');
        $translation->setTranslation('Clothing');
        $translation->setCategory($category);
        $category->addTranslation($translation);
        $germanTranslation = new CategoryTranslation();
        $germanTranslation->setLocale('de');
        $germanTranslation->setTranslation('Kleidung');
        $germanTranslation->setCategory($category);
        $category->addTranslation($germanTranslation);
        $this->entityManager()->persist($category);
        $this->entityManager()->persist($translation);
        $this->entityManager()->persist($germanTranslation);
        $this->entityManager()->flush();
        $this->categoryId = $category->getId();

        foreach (['Cotton', 'Summer'] as $name) {
            $tag = new Tag();
            $tag->setName($name);
            $this->entityManager()->persist($tag);
            $this->entityManager()->flush();
            $this->tagIds[] = $tag->getId();
        }
    }

    /**
     * Creates the product with excerpt and seo data and publishes it in english and german.
     *
     * @param array<string, string|float|bool> $values by attribute key, the text one only for english
     */
    private function createProduct(string $url, string $title, array $values, string $type = ProductInterface::TYPE_PRODUCT): string
    {
        $productId = null;
        foreach (['en', 'de'] as $locale) {
            $isEnglish = 'en' === $locale;
            $localeTitle = $isEnglish ? $title : 'Hemd';
            $data = [
                'locale' => $locale,
                'type' => $type,
                'productFamily' => $this->familyId,
                'template' => 'product',
                'title' => $localeTitle,
                'description' => '<p>' . $localeTitle . ' description</p>',
                'url' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS === $type ? null : ($isEnglish ? $url : '/hemd'),
                'excerpt' => [
                    'title' => $localeTitle . ' excerpt',
                    'description' => 'Excerpt description',
                ],
                'excerptTags' => $this->tagIds,
                'excerptCategories' => [$this->categoryId],
                'seo' => [
                    'title' => $localeTitle . ' seo',
                    'description' => 'Seo description',
                    'keywords' => 'shirt, cotton',
                ],
                'attributes' => $this->attributeValues($values, $locale),
            ];

            if (null === $productId) {
                $this->admin->request('POST', '/admin/api/products.json?locale=' . $locale, [], [], [], \json_encode($data) ?: null);
                $this->assertHttpStatusCode(201, $this->admin->getResponse());
                $productId = $this->responseId();
            }

            $this->admin->request('PUT', '/admin/api/products/' . $productId . '.json?locale=' . $locale . '&action=publish', [], [], [], \json_encode($data) ?: null);
            $this->assertHttpStatusCode(200, $this->admin->getResponse());
        }

        return $productId;
    }

    /**
     * @param array<string, string> $values by attribute key
     */
    private function createVariant(string $productId, string $url, array $values): void
    {
        $variantId = null;
        foreach (['en', 'de'] as $locale) {
            $data = [
                'locale' => $locale,
                'title' => 'en' === $locale ? 'Shirt blue' : 'Hemd blau',
                'url' => 'en' === $locale ? $url : '/hemd-blau',
                'attributes' => $this->attributeValues($values, $locale),
            ];

            if (null === $variantId) {
                $this->admin->request('POST', '/admin/api/products/' . $productId . '/variants.json?locale=' . $locale, [], [], [], \json_encode($data) ?: null);
                $this->assertHttpStatusCode(201, $this->admin->getResponse());
                $variantId = $this->responseId();
            }

            $this->admin->request('PUT', '/admin/api/products/' . $productId . '/variants/' . $variantId . '.json?locale=' . $locale, [], [], [], \json_encode($data) ?: null);
            $this->assertHttpStatusCode(200, $this->admin->getResponse());

            $this->admin->request('POST', '/admin/api/products/' . $productId . '/variants/' . $variantId . '.json?action=publish&locale=' . $locale);
            $this->assertHttpStatusCode(200, $this->admin->getResponse());
        }
    }

    /**
     * @param array<string, string|float|bool> $values by attribute key
     *
     * @return array<string, string|float|bool> by attribute uuid
     */
    private function attributeValues(array $values, string $locale): array
    {
        $byUuid = [];
        foreach ($values as $key => $value) {
            if ('note' === $key) {
                $value = 'en' === $locale ? $value : 'Handgemacht';
            }
            $byUuid[$this->attributeIds[$key]] = $value;
        }

        return $byUuid;
    }

    private function responseId(): string
    {
        $data = \json_decode((string) $this->admin->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertIsString($data['id'] ?? null);

        return $data['id'];
    }
}
