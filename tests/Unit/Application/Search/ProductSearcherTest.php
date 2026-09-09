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
        // Mirrors the flags of config/schemas/products.php: `title` is neither filterable, sortable
        // nor a facet, `changedAt` is the sortable field.
        $this->schema = new Schema([
            ProductIndex::NAME => new Index(ProductIndex::NAME, [
                'id' => new Field\IdentifierField('id'),
                'type' => new Field\TextField('type', searchable: false, filterable: true),
                'locale' => new Field\TextField('locale', searchable: false, filterable: true),
                'webspaces' => new Field\TextField('webspaces', multiple: true, searchable: false, filterable: true),
                'title' => new Field\TextField('title'),
                'content' => new Field\TextField('content', multiple: true),
                'status' => new Field\TextField('status', searchable: false, filterable: true, facet: true),
                'changedAt' => new Field\DateTimeField('changedAt', sortable: true),
                'attr_weight' => new Field\FloatField('attr_weight', multiple: true, filterable: true, facet: true),
            ]),
        ]);
        $this->engine = new Engine(new MemoryAdapter(), $this->schema);
        $this->engine->createIndex(ProductIndex::NAME);

        $documents = [
            ['id' => 'p1', 'type' => ProductInterface::TYPE_PRODUCT, 'locale' => 'en', 'webspaces' => ['ws'], 'title' => 'Plain cable', 'content' => [], 'status' => 'available', 'changedAt' => '2024-01-01 00:00:00', 'attr_weight' => [1.0]],
            ['id' => 'p2', 'type' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS, 'locale' => 'en', 'webspaces' => ['ws'], 'title' => 'Parent cable', 'content' => [], 'status' => 'available', 'changedAt' => '2024-01-04 00:00:00', 'attr_weight' => [2.0, 3.0]],
            ['id' => 'v1', 'type' => ProductInterface::TYPE_VARIANT, 'locale' => 'en', 'webspaces' => ['ws'], 'title' => 'Variant cable red', 'content' => [], 'status' => 'available', 'changedAt' => '2024-01-02 00:00:00', 'attr_weight' => [2.0]],
            ['id' => 'v2', 'type' => ProductInterface::TYPE_VARIANT, 'locale' => 'en', 'webspaces' => ['ws'], 'title' => 'Variant cable blue', 'content' => [], 'status' => 'discontinued', 'changedAt' => '2024-01-03 00:00:00', 'attr_weight' => [3.0]],
            ['id' => 'de', 'type' => ProductInterface::TYPE_PRODUCT, 'locale' => 'de', 'webspaces' => ['ws'], 'title' => 'Kabel', 'content' => [], 'status' => 'available', 'changedAt' => '2024-01-01 00:00:00', 'attr_weight' => [1.0]],
            ['id' => 'other', 'type' => ProductInterface::TYPE_PRODUCT, 'locale' => 'en', 'webspaces' => ['other'], 'title' => 'Other cable', 'content' => [], 'status' => 'available', 'changedAt' => '2024-01-01 00:00:00', 'attr_weight' => [1.0]],
        ];
        foreach ($documents as $document) {
            $this->engine->saveDocument(ProductIndex::NAME, $document);
        }
    }

    public function testDefaultHitsAreVariantsAndVariantLessProductsInLocaleAndWebspace(): void
    {
        $result = $this->searcher()->search(new ProductSearchQuery('en', 'ws', 'cable'));

        $this->assertEqualsCanonicalizing(['p1', 'v1', 'v2'], $this->ids($result));
    }

    public function testProductsPolicyExcludesVariants(): void
    {
        $result = $this->searcher()->search(new ProductSearchQuery('en', 'ws', hits: ProductSearchQuery::HITS_PRODUCTS));

        $this->assertEqualsCanonicalizing(['p1', 'p2'], $this->ids($result));
    }

    public function testEqualsAndRangeFilters(): void
    {
        $searcher = $this->searcher();

        $result = $searcher->search(new ProductSearchQuery('en', 'ws', equals: ['status' => 'available'], ranges: ['attr_weight' => ['min' => 2.0]]));
        $this->assertSame(['v1'], $this->ids($result));

        $result = $searcher->search(new ProductSearchQuery('en', 'ws', equals: ['status' => ['available', 'discontinued']], ranges: ['attr_weight' => ['min' => 2.0, 'max' => 2.5]]));
        $this->assertSame(['v1'], $this->ids($result));
    }

    /**
     * An empty value reaches the searcher from a custom controller. It is no filter, not a filter
     * that matches nothing.
     */
    public function testEmptyEqualsValuesAreIgnored(): void
    {
        $searcher = $this->searcher();

        $result = $searcher->search(new ProductSearchQuery('en', 'ws', equals: ['status' => '']));
        $this->assertEqualsCanonicalizing(['p1', 'v1', 'v2'], $this->ids($result));

        $result = $searcher->search(new ProductSearchQuery('en', 'ws', equals: ['status' => []]));
        $this->assertEqualsCanonicalizing(['p1', 'v1', 'v2'], $this->ids($result));

        $result = $searcher->search(new ProductSearchQuery('en', 'ws', equals: ['status' => ['', 'available']]));
        $this->assertEqualsCanonicalizing(['p1', 'v1'], $this->ids($result));
    }

    public function testSortingAndPaging(): void
    {
        $searcher = $this->searcher();

        $result = $searcher->search(new ProductSearchQuery('en', 'ws', limit: 2, sortBy: ['changedAt' => 'asc']));
        $this->assertSame(['p1', 'v1'], $this->ids($result));

        $result = $searcher->search(new ProductSearchQuery('en', 'ws', page: 2, limit: 2, sortBy: ['changedAt' => 'asc']));
        $this->assertSame(['v2'], $this->ids($result));

        // The memory adapter counts the returned page, so only an unpaged query shows the match count.
        $result = $searcher->search(new ProductSearchQuery('en', 'ws', limit: 100));
        $this->assertSame(3, $result->total());
    }

    public function testFacets(): void
    {
        $result = $this->searcher()->search(new ProductSearchQuery('en', 'ws', countFacets: ['status'], minMaxFacets: ['attr_weight']));

        $facets = $result->facets();
        $status = $facets['status'];
        $this->assertIsArray($status);
        $this->assertSame(['available' => 2, 'discontinued' => 1], $status['count']);

        // The memory adapter reduces min/max over the whole field value, so a multiple field keeps its list.
        $weight = $facets['attr_weight'];
        $this->assertIsArray($weight);
        $this->assertSame([1.0], $weight['min']);
        $this->assertSame([3.0], $weight['max']);
    }

    public function testAllPolicyReturnsProductsAndVariants(): void
    {
        $result = $this->searcher()->search(new ProductSearchQuery('en', 'ws', hits: ProductSearchQuery::HITS_ALL));

        $this->assertEqualsCanonicalizing(['p1', 'p2', 'v1', 'v2'], $this->ids($result));
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

    public function testUnknownHitPolicyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ProductSearchQuery('en', 'ws', hits: 'unknown');
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
        $searcher = $this->searcher();

        $result = $searcher->search(new ProductSearchQuery('en', 'ws', equals: ['title' => 'nothing matches this']));
        $this->assertEqualsCanonicalizing(['p1', 'v1', 'v2'], $this->ids($result));

        $result = $searcher->search(new ProductSearchQuery('en', 'ws', ranges: ['title' => ['min' => 1.0, 'max' => 2.0]]));
        $this->assertEqualsCanonicalizing(['p1', 'v1', 'v2'], $this->ids($result));
    }

    public function testSortOnANonSortableFieldIsIgnored(): void
    {
        $result = $this->searcher()->search(new ProductSearchQuery('en', 'ws', sortBy: ['title' => 'asc']));

        // Sorted by title the order would be p1, v2, v1.
        $this->assertSame(['p1', 'v1', 'v2'], $this->ids($result));
    }

    public function testFacetOnAFieldWithoutTheFacetFlagIsIgnored(): void
    {
        $result = $this->searcher()->search(new ProductSearchQuery('en', 'ws', countFacets: ['title'], minMaxFacets: ['title']));

        $this->assertSame([], $result->facets());
    }

    private function searcher(): ProductSearcher
    {
        return new ProductSearcher($this->engine, $this->schema);
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
