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

namespace Sulu\Product\Application\Search;

use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Schema\Schema;
use CmsIg\Seal\Search\Condition\Condition;
use CmsIg\Seal\Search\Facet\Facet;
use CmsIg\Seal\Search\Result;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;

/**
 * Runs a catalogue query against the product documents of the website index. Resource key, locale
 * and webspace are always applied; the index only holds live content, so no stage filter is needed.
 *
 * Field names come from the outside, so every one is checked against the index schema and dropped
 * when the schema does not allow that use. An adapter would otherwise either fail the request or
 * take the name into its own filter syntax.
 */
final class ProductSearcher
{
    public function __construct(
        private readonly EngineInterface $engine,
        private readonly Schema $schema,
    ) {
    }

    public function search(ProductSearchQuery $query): Result
    {
        $index = $this->schema->indexes[ProductIndex::NAME]
            ?? throw new \RuntimeException(\sprintf('The index "%s" is missing from the schema.', ProductIndex::NAME));

        $search = $this->engine->createSearchBuilder(ProductIndex::NAME)
            ->addFilter(Condition::equal('resourceKey', ProductInterface::RESOURCE_KEY))
            ->addFilter(Condition::equal('locale', $query->locale))
            ->addFilter(Condition::equal('webspaces', $query->webspace));

        if ('' !== \trim($query->term)) {
            $search->addFilter(Condition::search(\trim($query->term)));
        }

        foreach ($query->equals as $field => $value) {
            $field = (string) $field;
            if (!\in_array($field, $index->filterableFields, true)) {
                continue;
            }

            // An empty value is no filter: filtering on it would return nothing.
            if (\is_array($value)) {
                $values = \array_values(\array_filter($value, static fn (string $item): bool => '' !== $item));
                if ([] !== $values) {
                    $search->addFilter(Condition::in($field, $values));
                }

                continue;
            }

            if ('' !== $value) {
                $search->addFilter(Condition::equal($field, $value));
            }
        }

        foreach ($query->ranges as $field => $range) {
            $field = (string) $field;
            if (!\in_array($field, $index->filterableFields, true)) {
                continue;
            }

            if (isset($range['min'])) {
                $search->addFilter(Condition::greaterThanEqual($field, $range['min']));
            }
            if (isset($range['max'])) {
                $search->addFilter(Condition::lessThanEqual($field, $range['max']));
            }
        }

        foreach ($query->countFacets as $field) {
            if (\in_array($field, $index->facetFields, true)) {
                $search->addFacet(Facet::count($field));
            }
        }
        foreach ($query->minMaxFacets as $field) {
            if (\in_array($field, $index->facetFields, true)) {
                $search->addFacet(Facet::minMax($field));
            }
        }

        foreach ($query->sortBy as $field => $direction) {
            $field = (string) $field;
            if (!\in_array($field, $index->sortableFields, true)) {
                continue;
            }

            $search->addSortBy($field, $direction);
        }

        $search->limit($query->limit)->offset(($query->page - 1) * $query->limit);
        $search->highlight(['title', 'content'], '<mark>', '</mark>');

        return $search->getResult();
    }
}
