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

namespace Sulu\Product\Tests\Unit\Application\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophet;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;
use Sulu\Product\Application\Mcp\ProductUrlHelper;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Tests\Unit\Fixture\ArrayMetadataProvider;
use Sulu\Product\Tests\Unit\Fixture\ProductUrlHelperFactory;
use Sulu\Route\Application\ResourceLocator\PathCleanup\PathCleanup;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorGenerator;
use Sulu\Route\Application\ResourceLocator\RouteSchemaEvaluator;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ProductUrlHelper::class)]
final class ProductUrlHelperTest extends TestCase
{
    public function testAPageTreeRouteAsksForAPageAndASuffix(): void
    {
        $helper = ProductUrlHelperFactory::create('page_tree_route');

        $this->assertTrue($helper->isPageBased());
        $this->assertStringContainsString('"suffix"', $helper->instruction());
        $this->assertStringContainsString('"suffix"', $helper->warning([], 'en'));
    }

    public function testThePlainRouteTypeAsksForAString(): void
    {
        $helper = ProductUrlHelperFactory::create('route');

        $this->assertFalse($helper->isPageBased());
        $this->assertStringContainsString('path string', $helper->instruction());
        $this->assertStringNotContainsString('"page"', $helper->warning([], 'en'));
    }

    public function testTheExampleFollowsTheRouteSchema(): void
    {
        $instruction = ProductUrlHelperFactory::create('route')->instruction(['title' => 'Dup Check'], 'en');

        $this->assertStringContainsString('"/products/dup-check"', $instruction);
    }

    public function testTheExampleIsUniqueAmongTheExistingRoutes(): void
    {
        $helper = ProductUrlHelperFactory::create('route', ['/products/dup-check']);

        $this->assertStringContainsString('"/products/dup-check-1"', $helper->warning(['title' => 'Dup Check'], 'en', 'uuid-1'));
    }

    public function testTheExampleUsesTheSlugOfTheAdmin(): void
    {
        $instruction = ProductUrlHelperFactory::create('route')->instruction(['title' => 'Hemd für Männer'], 'de');

        $this->assertStringContainsString('"/products/hemd-fuer-maenner"', $instruction);
    }

    public function testTheExampleBuildsTheUrlFromTheRoutePartsOnly(): void
    {
        $helper = ProductUrlHelperFactory::create('route');

        $warning = $helper->warning([
            'title' => 'Probe',
            'uuid' => 'd9b8a1c2-0000-4000-8000-000000000001',
            'code' => 'SCH-3',
            'description' => 'A long text that does not belong in a url.',
            'excerpt' => ['x' => 'y'],
        ], 'en');

        $this->assertStringContainsString('"/products/probe"', $warning);
        $this->assertStringNotContainsString('sch-3', $warning);
    }

    public function testWithoutARoutePartTheExampleIsTheSiblingPattern(): void
    {
        $form = new FormMetadata();
        $form->addItem(new FieldMetadata('title'));
        $provider = new ArrayMetadataProvider([ProductInterface::FORM_KEY => $form]);

        $helper = ProductUrlHelperFactory::create('route', [], "/products/{implode('-', object)}", $provider);

        $this->assertStringContainsString('e.g. the pattern of a sibling product', $helper->warning(['title' => 'Probe'], 'en'));
    }

    public function testAMissingFormLeavesTheSiblingPattern(): void
    {
        $helper = ProductUrlHelperFactory::create('route', [], "/products/{implode('-', object)}", new ArrayMetadataProvider());

        $this->assertStringContainsString('e.g. the pattern of a sibling product', $helper->warning(['title' => 'Probe'], 'en'));
    }

    public function testAFormOfAnotherKindLeavesTheSiblingPattern(): void
    {
        $provider = (new ArrayMetadataProvider())->setDefault($this->createStub(MetadataInterface::class));
        $helper = ProductUrlHelperFactory::create('route', [], "/products/{implode('-', object)}", $provider);

        $this->assertStringContainsString('e.g. the pattern of a sibling product', $helper->warning(['title' => 'Probe'], 'en'));
    }

    public function testAWarningWithATitleShowsTheExample(): void
    {
        $this->assertStringContainsString('"/products/shirt"', ProductUrlHelperFactory::create('route')->warning(['title' => 'Shirt'], 'en'));
        $this->assertStringContainsString('sibling product', ProductUrlHelperFactory::create('route')->warning(['title' => 5], 'en'));
    }

    public function testWithoutATitleTheExampleIsTheSiblingPattern(): void
    {
        $this->assertStringContainsString('e.g. the pattern of a sibling product', ProductUrlHelperFactory::create('route')->instruction());
        $this->assertStringContainsString('e.g. the pattern of a sibling product', ProductUrlHelperFactory::create('route')->instruction(['title' => '  ', 'code' => 5]));
    }

    public function testATitleWithoutASlugLeavesTheUrlWithoutASuffix(): void
    {
        $data = ['title' => '!!!', 'url' => ['page' => ['uuid' => 'p', 'path' => '/products']]];

        $this->assertSame($data, ProductUrlHelperFactory::create()->completeUrlSuffix($data, 'en'));
    }

    public function testTheSuffixMatchesTheSlugOfTheAdmin(): void
    {
        $data = ProductUrlHelperFactory::create()->completeUrlSuffix(
            ['title' => 'Hemd für Männer', 'url' => ['page' => ['uuid' => 'p', 'path' => '/products']]],
            'de',
        );

        $url = $data['url'] ?? null;
        $this->assertIsArray($url);
        $this->assertSame('/hemd-fuer-maenner', $url['suffix'] ?? null);
    }

    public function testAGivenSuffixStays(): void
    {
        $data = ['title' => 'Shirt', 'url' => ['page' => ['uuid' => 'p', 'path' => '/products'], 'suffix' => '/custom']];

        $this->assertSame($data, ProductUrlHelperFactory::create()->completeUrlSuffix($data, 'en'));
    }

    public function testAStringUrlIsNeverCompleted(): void
    {
        $data = ['title' => 'Shirt', 'url' => '/hat-red'];

        $this->assertSame($data, ProductUrlHelperFactory::create('route')->completeUrlSuffix($data, 'en'));
    }

    public function testATakenSuffixGetsANumberBelowThePage(): void
    {
        $helper = ProductUrlHelperFactory::create('page_tree_route', ['/products/dup-check'], pageRouteSlug: '/products');

        $data = $helper->completeUrlSuffix(
            ['title' => 'Dup Check', 'url' => ['page' => ['uuid' => 'page-uuid', 'path' => '/products']]],
            'en',
        );

        $url = $data['url'] ?? null;
        $this->assertIsArray($url);
        $this->assertSame('/dup-check-1', $url['suffix'] ?? null);
    }

    public function testATakenSuffixOfAnotherPageStaysFree(): void
    {
        $helper = ProductUrlHelperFactory::create('page_tree_route', ['/other/dup-check'], pageRouteSlug: '/products');

        $data = $helper->completeUrlSuffix(
            ['title' => 'Dup Check', 'url' => ['page' => ['uuid' => 'page-uuid', 'path' => '/products']]],
            'en',
        );

        $url = $data['url'] ?? null;
        $this->assertIsArray($url);
        $this->assertSame('/dup-check', $url['suffix'] ?? null);
    }

    public function testTheSuffixIgnoresTheProductPathOfTheRouteSchema(): void
    {
        $data = ProductUrlHelperFactory::create()->completeUrlSuffix(
            ['title' => 'Shirt', 'url' => ['page' => ['uuid' => 'p', 'path' => '/shop']]],
            'en',
        );

        $url = $data['url'] ?? null;
        $this->assertIsArray($url);
        $this->assertSame('/shirt', $url['suffix'] ?? null);
    }

    public function testAPageWithoutUuidLeavesTheUrl(): void
    {
        $data = ['title' => 'Shirt', 'url' => ['page' => ['path' => '/products']]];

        $this->assertSame($data, ProductUrlHelperFactory::create()->completeUrlSuffix($data, 'en'));
    }

    public function testTheSuffixOfTheProductItselfIsNotTaken(): void
    {
        $prophet = new Prophet();
        $repository = $prophet->prophesize(RouteRepositoryInterface::class);
        $repository->findOneBy(Argument::any())->willReturn(null);
        $repository->existBy(Argument::that(
            static fn (array $criteria): bool => ['resourceKey' => 'products', 'resourceId' => 'uuid-1'] === ($criteria['excludeResource'] ?? null),
        ))->willReturn(false);
        $repository->existBy(Argument::that(
            static fn (array $criteria): bool => '/shirt' === ($criteria['slug'] ?? null),
        ))->willReturn(true);
        $repository->existBy(Argument::any())->willReturn(false);

        $helper = new ProductUrlHelper(
            'page_tree_route',
            ['route_schema' => "/{implode('-', object)}"],
            new ResourceLocatorGenerator(
                $repository->reveal(),
                new RouteSchemaEvaluator(
                    $prophet->prophesize(TranslatorInterface::class)->reveal(),
                    new PathCleanup(new AsciiSlugger(), []),
                ),
            ),
            ProductUrlHelperFactory::detailsForm(),
        );

        $data = $helper->completeUrlSuffix(
            ['title' => 'Shirt', 'url' => ['page' => ['uuid' => 'p', 'path' => '/products']]],
            'en',
            'uuid-1',
        );

        $url = $data['url'] ?? null;
        $this->assertIsArray($url);
        $this->assertSame('/shirt', $url['suffix'] ?? null);
    }

    public function testWithoutARoutePartTheSuffixComesFromTheTitle(): void
    {
        $form = new FormMetadata();
        $form->addItem(new FieldMetadata('title'));
        $provider = new ArrayMetadataProvider([ProductInterface::FORM_KEY => $form]);
        $helper = ProductUrlHelperFactory::create('page_tree_route', [], "/products/{implode('-', object)}", $provider);

        $data = $helper->completeUrlSuffix(
            ['title' => 'Shirt', 'url' => ['page' => ['uuid' => 'p', 'path' => '/products']]],
            'en',
        );

        $url = $data['url'] ?? null;
        $this->assertIsArray($url);
        $this->assertSame('/shirt', $url['suffix'] ?? null);
    }
}
