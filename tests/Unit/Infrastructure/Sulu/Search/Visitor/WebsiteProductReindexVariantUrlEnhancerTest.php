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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Search\Visitor;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Application\Routing\VariantUrlBuilder;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductReindexVariantUrlEnhancer;

#[CoversClass(WebsiteProductReindexVariantUrlEnhancer::class)]
class WebsiteProductReindexVariantUrlEnhancerTest extends TestCase
{
    public function testEnhanceQuerySelectsTheSlugOfTheParentRoute(): void
    {
        $queryBuilder = new QueryBuilder($this->createStub(EntityManagerInterface::class));
        $queryBuilder->from('TestEntity', 'dimensionContent');

        $this->createEnhancer()->enhanceQuery($queryBuilder);

        $dql = $queryBuilder->getDQL();
        $this->assertStringContainsString('parentRoute.slug AS parentSlug', $dql);
        $this->assertStringContainsString('IDENTITY(parentDimensionContent.product) = IDENTITY(product.parent)', $dql);
        $this->assertStringContainsString('parentDimensionContent.locale = dimensionContent.locale', $dql);
        $this->assertStringContainsString('parentDimensionContent.stage = dimensionContent.stage', $dql);
        $this->assertStringContainsString('parentDimensionContent.version = dimensionContent.version', $dql);
    }

    public function testLinksAVariantByTheRouteOfItsProduct(): void
    {
        $result = $this->createEnhancer()->enhanceDocument(
            ['parentId' => 'shirt', 'code' => 'RED 1', 'parentSlug' => '/t-shirt'],
            ['url' => '/stale-variant-route'],
        );

        $this->assertSame(['url' => '/t-shirt?variant=RED%201'], $result);
    }

    public function testUsesTheConfiguredParameter(): void
    {
        $result = (new WebsiteProductReindexVariantUrlEnhancer(new VariantUrlBuilder('sku')))->enhanceDocument(
            ['parentId' => 'shirt', 'code' => 'RED', 'parentSlug' => '/t-shirt'],
            ['url' => ''],
        );

        $this->assertSame('/t-shirt?sku=RED', $result['url']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideVariantsWithoutAUrl(): iterable
    {
        yield 'product without route' => [['parentId' => 'shirt', 'code' => 'RED', 'parentSlug' => null]];
        yield 'variant without code' => [['parentId' => 'shirt', 'code' => null, 'parentSlug' => '/t-shirt']];
        yield 'variant with empty code' => [['parentId' => 'shirt', 'code' => '', 'parentSlug' => '/t-shirt']];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideVariantsWithoutAUrl')]
    public function testAVariantWithoutAProductRouteOrCodeHasNoUrl(array $row): void
    {
        $result = $this->createEnhancer()->enhanceDocument($row, ['url' => '/stale-variant-route']);

        $this->assertSame(['url' => ''], $result);
    }

    public function testKeepsTheUrlOfAProductWithoutParent(): void
    {
        $document = ['url' => '/plain'];

        $this->assertSame($document, $this->createEnhancer()->enhanceDocument(['parentId' => null, 'code' => 'PLAIN'], $document));
    }

    private function createEnhancer(): WebsiteProductReindexVariantUrlEnhancer
    {
        return new WebsiteProductReindexVariantUrlEnhancer(new VariantUrlBuilder('variant'));
    }
}
