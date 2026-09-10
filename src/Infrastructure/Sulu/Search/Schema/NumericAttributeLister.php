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
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * One index field per number and date attribute; a date is stored in the number column.
 * Text and options attributes need none, their values go into the text values field.
 * The attribute list is cached; NumericAttributeCacheInvalidator clears it.
 *
 * @internal
 */
class NumericAttributeLister
{
    public const CACHE_KEY = 'sulu_product.search.numeric_attribute_fields';

    /**
     * The cache entry expires as well as being invalidated, because the invalidator runs in the
     * kernel context that wrote the attribute and a per-context pool is not shared with the other.
     */
    public const CACHE_TTL = 300;

    private const NUMERIC_TYPES = [
        AttributeInterface::TYPE_NUMBER,
        AttributeInterface::TYPE_DATE,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, Field\FloatField>
     */
    public function getFields(): array
    {
        $fields = [];
        foreach ($this->getAttributeKeys() as $attributeKey) {
            $name = ProductIndex::numericField($attributeKey);

            // A field name must start with a letter and hold word characters only.
            if (1 !== \preg_match('/^[A-Za-z]\w*$/', $name)) {
                continue;
            }

            $fields[$name] = new Field\FloatField($name, multiple: true, searchable: false, filterable: true, facet: true);
        }

        return $fields;
    }

    /**
     * @return string[]
     */
    public function getAttributeKeys(): array
    {
        /** @var string[] */
        return $this->cache->get(self::CACHE_KEY, function(ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->loadAttributeKeys();
        });
    }

    public function clear(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    /**
     * @return string[]
     */
    private function loadAttributeKeys(): array
    {
        /** @var array<int, array{key: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('attribute.key AS key')
            ->from(Attribute::class, 'attribute')
            ->where('attribute.type IN (:types)')
            ->setParameter('types', self::NUMERIC_TYPES)
            ->orderBy('attribute.key', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return \array_column($rows, 'key');
    }
}
