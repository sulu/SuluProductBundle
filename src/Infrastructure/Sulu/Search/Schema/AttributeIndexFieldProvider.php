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
use CmsIg\Seal\Schema\Field\AbstractField;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * One filterable index field per number, date and options attribute.
 * The attribute list is cached; AttributeIndexFieldCacheInvalidator clears it.
 *
 * @internal
 */
class AttributeIndexFieldProvider
{
    public const CACHE_KEY = 'sulu_product.search.attribute_index_fields';

    /**
     * The cache entry expires as well as being invalidated, because the invalidator runs in the
     * kernel context that wrote the attribute and a per-context pool is not shared with the other.
     */
    public const CACHE_TTL = 300;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, AbstractField>
     */
    public function getFields(): array
    {
        /** @var array<int, array{key: string, type: string}> $rows */
        $rows = $this->cache->get(self::CACHE_KEY, function(ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->loadRows();
        });

        $fields = [];
        foreach ($rows as $row) {
            switch ($row['type']) {
                case AttributeInterface::TYPE_NUMBER:
                case AttributeInterface::TYPE_DATE:
                    $name = ProductIndex::attributeField($row['key']);
                    $fields[$name] = new Field\FloatField($name, multiple: true, filterable: true, facet: true);
                    break;
                case AttributeInterface::TYPE_OPTIONS:
                    $name = ProductIndex::optionField($row['key']);
                    $fields[$name] = new Field\TextField($name, multiple: true, searchable: false, filterable: true, facet: true);
                    break;
            }
        }

        return $fields;
    }

    public function clear(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    /**
     * @return array<int, array{key: string, type: string}>
     */
    private function loadRows(): array
    {
        /** @var array<int, array{key: string, type: string}> */
        return $this->entityManager->createQueryBuilder()
            ->select('attribute.key AS key', 'attribute.type AS type')
            ->from(Attribute::class, 'attribute')
            ->orderBy('attribute.key', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
