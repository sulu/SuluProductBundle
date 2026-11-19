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

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\CategoryBundle\Entity\CategoryTranslation;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\ProductMainCategoryDataMapper;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(ProductMainCategoryDataMapper::class)]
class ProductMainCategoryTest extends SuluTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testMainCategoryIsAddedToAndRemovedFromExcerptCategories(): void
    {
        $main = $this->createCategory('main');
        $other = $this->createCategory('other');
        $id = $this->createProduct(['excerptCategories' => [$other]]);

        $data = $this->put($id, ['mainCategory' => $main, 'excerptCategories' => [$other]]);
        $this->assertSame($main, $data['mainCategory']);
        $this->assertEqualsCanonicalizing([$main, $other], $data['excerptCategories']);

        // a stale excerpt list sent along with the change must not bring the old main category back
        $data = $this->put($id, ['mainCategory' => null, 'excerptCategories' => [$main, $other]]);
        $this->assertNull($data['mainCategory']);
        $this->assertSame([$other], $data['excerptCategories']);
    }

    public function testMainCategoryCannotBeDeselectedFromExcerptCategories(): void
    {
        $main = $this->createCategory('main');
        $id = $this->createProduct([]);
        $this->put($id, ['mainCategory' => $main]);

        $data = $this->put($id, ['excerptCategories' => []]);

        $this->assertSame($main, $data['mainCategory']);
        $this->assertSame([$main], $data['excerptCategories']);
    }

    public function testMainCategoryIsPublished(): void
    {
        $main = $this->createCategory('main');
        $id = $this->createProduct([]);
        $this->put($id, ['mainCategory' => $main]);

        $this->client->request('POST', '/admin/api/products/' . $id . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();
        $live = $em->createQueryBuilder()
            ->select('dimensionContent')
            ->from(ProductDimensionContent::class, 'dimensionContent')
            ->innerJoin('dimensionContent.product', 'product')
            ->where('product.uuid = :uuid')
            ->andWhere('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.locale = :locale')
            ->setParameter('uuid', $id)
            ->setParameter('stage', 'live')
            ->setParameter('locale', 'en')
            ->getQuery()
            ->getSingleResult();

        $this->assertInstanceOf(ProductDimensionContent::class, $live);
        $this->assertSame($main, $live->getMainCategory()?->getId());
        $this->assertSame([$main], $live->getExcerptCategoryIds());
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function createProduct(array $extra): string
    {
        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Family',
            'key' => \uniqid('family-'),
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $family = $this->decode()['id'];

        $this->client->request('POST', '/admin/api/products.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'title' => 'Product',
            'url' => '/main-category-product',
            'productFamily' => $family,
            'type' => 'product',
        ] + $extra) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $id = $this->decode()['id'];
        $this->assertIsString($id);

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function put(string $id, array $data): array
    {
        $this->client->request('PUT', '/admin/api/products/' . $id . '.json?locale=en', [], [], [], \json_encode(
            ['locale' => 'en'] + $data,
        ) ?: null);
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        return $this->decode();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function createCategory(string $key): int
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $category = new Category();
        $category->setKey($key);
        $category->setDefaultLocale('en');
        $translation = new CategoryTranslation();
        $translation->setLocale('en');
        $translation->setTranslation(\ucfirst($key));
        $translation->setCategory($category);
        $category->addTranslation($translation);
        $em->persist($category);
        $em->persist($translation);
        $em->flush();

        return (int) $category->getId();
    }
}
