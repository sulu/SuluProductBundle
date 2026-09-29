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

namespace Sulu\Product\Infrastructure\Sulu\Search\Schema;

use CmsIg\Seal\Schema\Field;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Loader\LoaderInterface;
use CmsIg\Seal\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductAttributesReindexProviderEnhancer as Enhancer;

/**
 * Adds the `product` object field to the website index: the family, the text values of the
 * filterable options attributes, and one field per filterable number or date attribute; a date is
 * stored in the number column.
 *
 * @internal
 */
final class ProductSchemaLoader implements LoaderInterface
{
    private const NUMERIC_TYPES = [
        AttributeInterface::TYPE_NUMBER,
        AttributeInterface::TYPE_DATE,
    ];

    public function __construct(
        private readonly LoaderInterface $inner,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function load(): Schema
    {
        $schema = $this->inner->load();

        $index = $schema->indexes['website'] ?? null;
        if (null === $index) {
            return $schema;
        }

        $fields = $index->fields;
        $fields[Enhancer::FIELD] = new Field\ObjectField(Enhancer::FIELD, [
            Enhancer::PRODUCT_FAMILY_ID_FIELD => new Field\TextField(Enhancer::PRODUCT_FAMILY_ID_FIELD, searchable: false, filterable: true, facet: true),
            Enhancer::TEXT_VALUES_FIELD => new Field\TextField(Enhancer::TEXT_VALUES_FIELD, multiple: true, searchable: false, filterable: true, facet: true),
            Enhancer::NUMERIC_VALUES_FIELD => new Field\ObjectField(Enhancer::NUMERIC_VALUES_FIELD, $this->loadNumericFields()),
        ]);

        $indexes = $schema->indexes;
        $indexes['website'] = new Index($index->name, $fields, $index->options);

        return new Schema($indexes);
    }

    /**
     * @return array<string, Field\FloatField>
     */
    private function loadNumericFields(): array
    {
        /** @var array<int, array{key: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('attribute.key AS key')
            ->from(Attribute::class, 'attribute')
            ->where('attribute.type IN (:types)')
            ->andWhere('attribute.filterable = true')
            ->setParameter('types', self::NUMERIC_TYPES)
            ->orderBy('attribute.key', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $fields = [];
        foreach (\array_column($rows, 'key') as $attributeKey) {
            $name = Enhancer::numericField($attributeKey);
            $fields[$name] = new Field\FloatField($name, multiple: true, searchable: false, filterable: true, facet: true);
        }

        return $fields;
    }
}
