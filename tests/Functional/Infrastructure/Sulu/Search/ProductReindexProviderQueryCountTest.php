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

namespace Sulu\Product\Tests\Functional\Infrastructure\Sulu\Search;

use CmsIg\Seal\Reindex\ReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;
use Doctrine\Bundle\DoctrineBundle\Middleware\BacktraceDebugDataHolder;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The query count of a reindex must not grow with the number of products, or a full reindex runs
 * one query per document.
 */
class ProductReindexProviderQueryCountTest extends SuluTestCase
{
    private KernelBrowser $client;

    /**
     * @var array{weight: int, colour: int, note: int}
     */
    private array $attributeIds;

    private string $familyId;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    public function testWebsiteProviderQueryCountDoesNotGrowWithTheProducts(): void
    {
        $this->assertQueryCountDoesNotGrow('sulu_product.website_product_reindex_provider', 'website');
    }

    public function testAdminProviderQueryCountDoesNotGrowWithTheProducts(): void
    {
        $this->assertQueryCountDoesNotGrow('sulu_product.admin_product_reindex_provider', 'admin');
    }

    private function assertQueryCountDoesNotGrow(string $providerId, string $index): void
    {
        self::purgeDatabase();
        $this->createAttributesAndFamily();

        for ($i = 0; $i < 3; ++$i) {
            $this->createProductWithVariant();
        }
        [$threeCount, $threeQueries, $threeDocuments] = $this->countReindexQueries($providerId, $index);

        $this->createProductWithVariant();
        [$fourCount, $fourQueries, $fourDocuments] = $this->countReindexQueries($providerId, $index);

        // Guards against a provider that yields nothing and so runs no per-document queries at all.
        $this->assertSame($threeDocuments + 1, $fourDocuments, 'The fourth product adds one document.');
        $this->assertSame(
            $threeCount,
            $fourCount,
            "Reindexing 4 products ran more queries than reindexing 3.\n\n3 products:\n"
            . \implode("\n", $threeQueries) . "\n\n4 products:\n" . \implode("\n", $fourQueries),
        );
    }

    /**
     * @return array{int, list<string>, int} query count, the queries, document count
     */
    private function countReindexQueries(string $providerId, string $index): array
    {
        $container = self::getContainer();
        /** @var ReindexProviderInterface $provider */
        $provider = $container->get($providerId);
        /** @var BacktraceDebugDataHolder $debugDataHolder */
        $debugDataHolder = $container->get('doctrine.debug_data_holder');

        self::getEntityManager()->clear();
        $debugDataHolder->reset();

        $documents = \iterator_to_array($provider->provide(ReindexConfig::create()->withIndex($index)), false);

        /** @var list<array{sql: string}> $queries */
        $queries = $debugDataHolder->getData()['default'] ?? [];

        return [\count($queries), \array_column($queries, 'sql'), \count($documents)];
    }

    /**
     * Both dimension contents carry attribute values, so every enhancer query has rows to load.
     */
    private function createAttributesAndFamily(): void
    {
        $this->attributeIds = [
            'weight' => $this->createAttribute('weight', 'Weight', AttributeInterface::TYPE_NUMBER, false),
            'colour' => $this->createAttribute('colour', 'Colour', AttributeInterface::TYPE_OPTIONS, false, ['red' => 'Red']),
            'note' => $this->createAttribute('note', 'Note', AttributeInterface::TYPE_TEXT, true),
        ];

        $attributes = [];
        foreach ($this->attributeIds as $key => $attributeId) {
            $attributes[$attributeId] = [
                'enabled' => true,
                'required' => false,
                'variantSpecific' => 'colour' === $key,
            ];
        }

        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Query Count Family',
            'attributes' => $attributes,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $this->familyId = $this->responseId();
    }

    /**
     * A parent is an admin document and its variant a website document; the variant makes the
     * website provider load the parent's webspaces, slug and shared values.
     */
    private function createProductWithVariant(): void
    {
        /** @var int $counter */
        static $counter = 0;
        ++$counter;

        $this->client->request('POST', '/admin/api/products.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'title' => 'Parent ' . $counter,
            'url' => '/query-count-product-' . $counter,
            'productFamily' => $this->familyId,
            'type' => ProductInterface::TYPE_PRODUCT_WITH_VARIANTS,
            'details' => ['shortDescription' => '<p>Short description</p>'],
            'excerpt' => ['description' => 'Excerpt'],
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $parentId = $this->responseId();

        $this->client->request('PUT', '/admin/api/products/' . $parentId . '.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'attributes' => [$this->attributeIds['weight'] => 2.5, $this->attributeIds['note'] => 'Gold plated'],
        ]) ?: null);
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->client->request('POST', '/admin/api/products/' . $parentId . '/variants.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'code' => 'QUERY-COUNT-VARIANT-' . $counter,
            'title' => 'Variant ' . $counter,
            'url' => '/query-count-variant-' . $counter,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $variantId = $this->responseId();

        $this->client->request('PUT', '/admin/api/products/' . $parentId . '/variants/' . $variantId . '.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'attributes' => [$this->attributeIds['colour'] => 'red'],
        ]) ?: null);
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        $this->client->request('POST', '/admin/api/products/' . $parentId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    /**
     * @param array<string, string> $options option key => english name
     */
    private function createAttribute(string $key, string $name, string $type, bool $localized, array $options = []): int
    {
        $container = self::getContainer();
        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $group = $groupRepository->create();
        $groupRepository->save($group);

        $attribute = $attributeRepository->create($group);
        $attribute->setKey($key);
        $attribute->setType($type);
        $attribute->setLocalized($localized);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', $name));
        foreach ($options as $optionKey => $optionName) {
            $option = new AttributeOption($attribute, $optionKey);
            $option->addTranslation(new AttributeOptionTranslation($option, 'en', $optionName));
            $attribute->addOption($option);
        }
        $attributeRepository->save($attribute);
        $entityManager->flush();

        return $attribute->getId();
    }

    private function responseId(): string
    {
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }
}
