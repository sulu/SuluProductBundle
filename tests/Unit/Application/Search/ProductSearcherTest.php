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

namespace Sulu\Product\Tests\Unit\Application\Search;

use CmsIg\Seal\Adapter\Memory\MemoryAdapter;
use CmsIg\Seal\Engine;
use CmsIg\Seal\Schema\Field;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Schema;
use CmsIg\Seal\Search\Condition;
use CmsIg\Seal\Search\Facet;
use CmsIg\Seal\Search\Search;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Application\Search\ProductSearcher;
use Sulu\Product\Application\Search\ProductSearchQuery;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;

#[CoversClass(ProductSearcher::class)]
#[CoversClass(ProductSearchQuery::class)]
class ProductSearcherTest extends TestCase
{
    private Engine $engine;

    private Schema $schema;

    protected function setUp(): void
    {
        // Mirrors Sulu's website index plus config/schemas/website.php: `title` is neither
        // filterable, sortable nor a facet, `product.changedAt` is the sortable field.
        $this->schema = new Schema([
            ProductIndex::NAME => new Index(ProductIndex::NAME, [
                'id' => new Field\IdentifierField('id'),
                'resourceKey' => new Field\TextField('resourceKey', searchable: false, filterable: true),
                'locale' => new Field\TextField('locale', searchable: false, filterable: true),
                'webspaces' => new Field\TextField('webspaces', multiple: true, searchable: false, filterable: true),
                'title' => new Field\TextField('title'),
                'content' => new Field\TextField('content', multiple: true),
                ProductIndex::FIELD => new Field\ObjectField(ProductIndex::FIELD, [
                    'status' => new Field\TextField('status', searchable: false, filterable: true, facet: true),
                    'changedAt' => new Field\DateTimeField('changedAt', searchable: false, sortable: true),
                    ProductIndex::TEXT_VALUES_FIELD => new Field\TextField(ProductIndex::TEXT_VALUES_FIELD, multiple: true, searchable: false, filterable: true, facet: true),
                    ProductIndex::NUMERIC_VALUES_FIELD => new Field\ObjectField(ProductIndex::NUMERIC_VALUES_FIELD, [
                        'weight' => new Field\FloatField('weight', multiple: true, searchable: false, filterable: true, facet: true),
                    ]),
                ]),
            ]),
        ]);
        $this->engine = new Engine(new MemoryAdapter(), $this->schema);
        $this->engine->createIndex(ProductIndex::NAME);

        $documents = [
            $this->document('p1', 'Plain cable', 'available', '2024-01-01', ['colour:red'], [1.0]),
            $this->document('v1', 'Variant cable red', 'available', '2024-01-02', ['colour:red'], [2.0]),
            $this->document('v2', 'Variant cable blue', 'discontinued', '2024-01-03', ['colour:blue'], [3.0]),
            $this->document('de', 'Kabel', 'available', '2024-01-01', [], [1.0], locale: 'de'),
            $this->document('other', 'Other cable', 'available', '2024-01-01', [], [1.0], webspace: 'other'),
            // A page of the same webspace shares the index and must stay out of a product search.
            [
                'id' => 'page',
                'resourceKey' => 'pages',
                'locale' => 'en',
                'webspaces' => ['ws'],
                'title' => 'Cable page',
                'content' => [],
            ],
        ];
        foreach ($documents as $document) {
            $this->engine->saveDocument(ProductIndex::NAME, $document);
        }
    }

    public function testHitsAreTheProductsOfTheLocaleAndWebspace(): void
    {
        $result = $this->searcher()->search(new ProductSearchQuery('en', 'ws', 'cable'));

        $this->assertEqualsCanonicalizing(['p1', 'v1', 'v2'], $this->ids($result));
    }

    /**
     * A product field is addressed by its path. An option or text attribute is filtered through
     * the shared text values field, so its attribute key is part of the value.
     */
    public function testEqualsInAndRangeFiltersAreBuiltForTheProductPaths(): void
    {
        $search = $this->recordedSearch(new ProductSearchQuery(
            'en',
            'ws',
            equals: [
                'product.status' => 'available',
                ProductIndex::textValuesPath() => [ProductIndex::textValue('colour', 'red'), ProductIndex::textValue('colour', 'blue')],
            ],
            ranges: [ProductIndex::numericValuePath('weight') => ['min' => 2.0, 'max' => 3.0]],
        ));

        $this->assertEquals(
            new Condition\EqualCondition('product.status', 'available'),
            $this->filter($search, Condition\EqualCondition::class, 'product.status'),
        );
        $this->assertEquals(
            new Condition\InCondition(ProductIndex::textValuesPath(), ['colour:red', 'colour:blue']),
            $this->filter($search, Condition\InCondition::class, ProductIndex::textValuesPath()),
        );
        $this->assertEquals(
            new Condition\GreaterThanEqualCondition(ProductIndex::numericValuePath('weight'), 2.0),
            $this->filter($search, Condition\GreaterThanEqualCondition::class, ProductIndex::numericValuePath('weight')),
        );
        $this->assertEquals(
            new Condition\LessThanEqualCondition(ProductIndex::numericValuePath('weight'), 3.0),
            $this->filter($search, Condition\LessThanEqualCondition::class, ProductIndex::numericValuePath('weight')),
        );
    }

    /**
     * An empty value reaches the searcher from a custom controller. It is no filter, not a filter
     * that matches nothing.
     */
    public function testEmptyEqualsValuesAreIgnored(): void
    {
        foreach (['', [], ['']] as $value) {
            $search = $this->recordedSearch(new ProductSearchQuery('en', 'ws', equals: ['product.status' => $value]));

            $this->assertNull($this->filter($search, Condition\EqualCondition::class, 'product.status'));
            $this->assertNull($this->filter($search, Condition\InCondition::class, 'product.status'));
        }

        $search = $this->recordedSearch(new ProductSearchQuery('en', 'ws', equals: ['product.status' => ['', 'available']]));
        $this->assertEquals(
            new Condition\InCondition('product.status', ['available']),
            $this->filter($search, Condition\InCondition::class, 'product.status'),
        );
    }

    public function testPaging(): void
    {
        $searcher = $this->searcher();

        $this->assertCount(2, $this->ids($searcher->search(new ProductSearchQuery('en', 'ws', limit: 2))));
        $this->assertCount(1, $this->ids($searcher->search(new ProductSearchQuery('en', 'ws', page: 2, limit: 2))));

        // The memory adapter counts the returned page, so only an unpaged query shows the match count.
        $this->assertSame(3, $searcher->search(new ProductSearchQuery('en', 'ws', limit: 100))->total());
    }

    public function testSortingIsBuiltForTheProductPath(): void
    {
        $search = $this->recordedSearch(new ProductSearchQuery('en', 'ws', sortBy: ['product.changedAt' => 'desc']));

        $this->assertSame(['product.changedAt' => 'desc'], $search->sortBys);
    }

    public function testFacetsAreBuiltForTheProductPaths(): void
    {
        $search = $this->recordedSearch(new ProductSearchQuery(
            'en',
            'ws',
            countFacets: ['product.status', ProductIndex::textValuesPath()],
            minMaxFacets: [ProductIndex::numericValuePath('weight')],
        ));

        $this->assertEquals([
            new Facet\CountFacet('product.status'),
            new Facet\CountFacet(ProductIndex::textValuesPath()),
            new Facet\MinMaxFacet(ProductIndex::numericValuePath('weight')),
        ], $search->facets);
    }

    public function testTermIsHighlighted(): void
    {
        $result = $this->searcher()->search(new ProductSearchQuery('en', 'ws', 'Plain'));

        foreach ($result as $document) {
            $formatted = $document['_formatted'];
            $this->assertIsArray($formatted);
            $this->assertSame('<mark>Plain</mark> cable', $formatted['title']);
        }
    }

    public function testNonPositivePagingIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProductSearchQuery('en', 'ws', page: 0);
    }

    /**
     * A field the schema does not mark filterable would reach the adapter's filter syntax, so it is
     * dropped instead of applied.
     */
    public function testFilterAndRangeOnANonFilterableFieldAreIgnored(): void
    {
        $search = $this->recordedSearch(new ProductSearchQuery(
            'en',
            'ws',
            equals: ['title' => 'nothing matches this'],
            ranges: ['product.attributes_numeric_values.unknown' => ['min' => 1.0]],
        ));

        // Only resource key, locale and webspace remain, the filters the searcher always applies.
        $this->assertCount(3, $search->filters);
    }

    public function testSortOnANonSortableFieldIsIgnored(): void
    {
        $search = $this->recordedSearch(new ProductSearchQuery('en', 'ws', sortBy: ['title' => 'asc']));

        $this->assertSame([], $search->sortBys);
    }

    public function testFacetOnAFieldWithoutTheFacetFlagIsIgnored(): void
    {
        $search = $this->recordedSearch(new ProductSearchQuery('en', 'ws', countFacets: ['title'], minMaxFacets: ['title']));

        $this->assertSame([], $search->facets);
    }

    private function searcher(): ProductSearcher
    {
        return new ProductSearcher($this->engine, $this->schema);
    }

    private function recordedSearch(ProductSearchQuery $query): Search
    {
        $adapter = new RecordingAdapter();
        (new ProductSearcher(new Engine($adapter, $this->schema), $this->schema))->search($query);

        $search = $adapter->search;
        $this->assertInstanceOf(Search::class, $search);

        return $search;
    }

    /**
     * @param class-string $condition
     */
    private function filter(Search $search, string $condition, string $field): ?object
    {
        foreach ($search->filters as $filter) {
            /* @phpstan-ignore-next-line property.notFound */
            if ($filter::class === $condition && $filter->field === $field) {
                return $filter;
            }
        }

        return null;
    }

    /**
     * @param string[] $textValues
     * @param float[] $weights
     *
     * @return array<string, mixed>
     */
    private function document(string $id, string $title, string $status, string $changedAt, array $textValues, array $weights, string $locale = 'en', string $webspace = 'ws'): array
    {
        return [
            'id' => $id,
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'locale' => $locale,
            'webspaces' => [$webspace],
            'title' => $title,
            'content' => [],
            ProductIndex::FIELD => [
                'status' => $status,
                'changedAt' => $changedAt . ' 00:00:00',
                ProductIndex::TEXT_VALUES_FIELD => $textValues,
                ProductIndex::NUMERIC_VALUES_FIELD => ['weight' => $weights],
            ],
        ];
    }

    /**
     * @return string[]
     */
    private function ids(\CmsIg\Seal\Search\Result $result): array
    {
        $ids = [];
        foreach ($result as $document) {
            $id = $document['id'];
            $this->assertIsString($id);
            $ids[] = $id;
        }

        return $ids;
    }
}
